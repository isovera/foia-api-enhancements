<?php

namespace Drupal\foia_api\EventSubscriber;

use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FoiaApiResponseSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse', 100];
    return $events;
  }

  /**
   * Serializes ResourceResponse responses' data, and removes that data.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onResponse(FilterResponseEvent $event) {
    // @todo Check that this is a jsonapi request
    // Check that there is a componentFilter passed to the request.
    $filters = \Drupal::request()->query->get('filter');
    $components = $filters['componentFilter']['value'] ?? FALSE;
    if (!$components) {
      return;
    }

    $response = $event->getResponse();
    $content = json_decode($response->getContent(), TRUE);

    $filtered_data_ids = $this->gatherIdsToRemove($content, $components);
    $content = $this->filterIncludes($content, $filtered_data_ids);

    foreach ($content['data'] as $delta => $report) {
      // Remove fields from node data that are not relationship fields.
      $content['data'][$delta]['attributes'] = array_intersect_key($report['attributes'], array_flip(['title']));
      foreach ($report['relationships'] as $field_name => $field) {
        $content['data'][$delta]['relationships'][$field_name]['data'] = $this->filterById($field['data'], $filtered_data_ids);
      }
    }

    $response = $response->setContent(json_encode($content));
    $event->setResponse($response);
  }

  /**
   * Get ids of unrelated data to remove from component data request responses.
   *
   * @param array $response_content
   *   The jsonapi response body content.
   * @param array $requested_components
   *   An array of agency component abbreviations from the value of the
   *   `filters[componentFilter]` query param.
   *
   * @return array
   *   An array of ids from the `included` section of the jsonapi response whose
   *   data is unrelated to the agency components requested, based on the filter
   *   value.
   */
  private function gatherIdsToRemove($response_content, $requested_components) {
    $agency_components_to_remove = $this->agencyComponentIdsToRemove($response_content, $requested_components);
    $component_data_to_remove = $this->componentDataIdsToRemove($response_content, $agency_components_to_remove);

    return array_merge($agency_components_to_remove, $component_data_to_remove);
  }

  /**
   * Get `included` ids of agency components that don't match filter values.
   *
   * @param array $response_content
   *   The jsonapi response body content.
   * @param array $requested_components
   *   An array of agency component abbreviations from the value of the
   *   `filters[componentFilter]` query param.
   *
   * @return array
   *   An array of agency_component ids from the `included` section of the
   *   jsonapi response whose data is unrelated to the agency components
   *   requested, based on the filter value.
   */
  private function agencyComponentIdsToRemove($response_content, $requested_components) {
    // Gather ids for the agency components that have not been requested via the
    // componentFilter value.  The resulting array will be a list of
    // agency_component ids from the response's `included` section.
    // IDs will then be used to check included paragraph entities for whether
    // or not they should be filtered from the response.
    $filtered_agency_components = array_filter($response_content['included'], function($include) use ($requested_components) {
      if ($include['type'] !== 'agency_component') {
        return false;
      }

      $abbreviation = $include['attributes']['abbreviation'] ?? FALSE;
      if (!$abbreviation) {
        return false;
      }

      return !in_array(strtolower($abbreviation), array_map('strtolower', $requested_components));
    });

    return array_column($filtered_agency_components, 'id');
  }

  /**
   * Get `included` ids of entities unrelated to the component filter values.
   *
   * @param array $response_content
   *   The jsonapi response body content.
   * @param array $agency_components_to_remove
   *   An array of agency component ids (from the jsonapi response's `included`
   *   section) that are unrelated to the requested components based on the
   *   componentFilter values. These can be built by the
   *   agencyComponentIdsToRemove() method.
   *
   * @see \Drupal\foia_api\EventSubscriber\FoiaApiResponseSubscriber::agencyComponentIdsToRemove().
   *
   * @return array
   *   An array of relationship data ids from the `included` section of the
   *   jsonapi response whose data is unrelated to the agency components
   *   requested, based on the filter value.
   */
  private function componentDataIdsToRemove($response_content, $agency_components_to_remove) {
    // Gather the component data that references any filtered agency component
    // based on the componentFilter values. The resulting array will be a list of
    // agency_component ids from the response's `included` section. IDs will
    // then be used to remove data references that don't relate to the requested
    // component from a responses `data.[].relationships` fields.
    $filtered_component_data = array_filter($response_content['included'], function($include) use ($agency_components_to_remove) {
      if ($include['type'] === 'agency_component' || $include['type'] == 'agency') {
        return false;
      }

      $agency_component = $include['relationships']['field_agency_component']['data']['id'] ?? FALSE;
      if (!$agency_component) {
        return false;
      }

      return in_array($agency_component, $agency_components_to_remove);
    });
    return array_column($filtered_component_data, 'id');
  }

  /**
   * Filter the `included` section of the response.
   *
   * @param array $content
   *   The response body of a jsonapi response.
   *
   * @param array $filtered_data_ids
   *   An array of jsonapi entity ids that are being removed from a response.
   *
   * @return array
   *   The response body after having removed includes that are being filtered
   *   out of the response.
   */
  protected function filterIncludes($content, array $filtered_data_ids) {
    $content['included'] = $this->filterById($content['included'], $filtered_data_ids);
    if (empty($content['included'])) {
      unset($content['included']);
    }
    return $content;
  }

  /**
   * Filter out values from a data array that may have an id or multiple values.
   *
   * @param array $data
   *   A piece of data from a jsonapi response such as an `included` item like
   *   an agency_component or a `data.[].relationships` field such as
   *   field_agency_components.  The data
   * @param array $filtered_data_ids
   *   An array of jsonapi entity ids that are being removed from a response.
   *
   * @return array
   *   The data array after having removed references to entities that should
   *   not be in the jsonapi response.
   */
  protected function filterById($data, $filtered_data_ids) {
    // Return the data as is if an id does not exist directly on the passed
    // array or the if it is not a multidimensional array of data that each has
    // an 'id' column.
    if (!isset($data['id']) && (empty(array_column($data, 'id'))) || count(array_column($data, 'id')) !== count($data)) {
      return $data;
    }

    if (isset($data['id'])) {
      return !in_array($data['id'], $filtered_data_ids) ? $data : [];
    }

    return array_values(array_filter($data, function($component) use ($filtered_data_ids) {
      return !in_array($component['id'], $filtered_data_ids);
    }));
  }

}
