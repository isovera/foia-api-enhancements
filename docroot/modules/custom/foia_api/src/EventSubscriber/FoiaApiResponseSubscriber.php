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

    // Gather ids for the agency components that have not been requested via the
    // componentFilter value.  The array will ultimately be a map of the
    // component's key value in the `included` section of the jsonapi response
    // and the id assigned to the entity by jsonapi.  Having the id will allow
    // checking relationship data in included paragraph entities to build a list
    // of references to component data for the requested components.
    $filtered_agency_components = array_filter($content['included'], function($include) use ($components) {
      if ($include['type'] !== 'agency_component') {
        return false;
      }

      $abbreviation = $include['attributes']['abbreviation'] ?? FALSE;
      if (!$abbreviation) {
        return false;
      }

      return !in_array(strtolower($abbreviation), array_map('strtolower', $components));
    });
    $filtered_agency_components = array_column($filtered_agency_components, 'id');

    // Gather the component data that references any filtered agency component
    // based on the componentFilter values. The array will ultimately
    // be a map of the entity's key value in the `included` section of the jsonapi response
    // and the id assigned to the entity by jsonapi.  Having the id will allow
    // checking relationship data in annual report node relationship fields to
    // remove references to component data that is not related to a requested
    // component.
    $filtered_component_data = array_filter($content['included'], function($include) use ($filtered_agency_components) {
      if ($include['type'] === 'agency_component' || $include['type'] == 'agency') {
        return false;
      }

      $agency_component = $include['relationships']['field_agency_component']['data']['id'] ?? FALSE;
      if (!$agency_component) {
        return false;
      }

      return in_array($agency_component, $filtered_agency_components);
    });
    $filtered_component_data = array_column($filtered_component_data, 'id');
    $filtered_data_ids = array_merge($filtered_agency_components, $filtered_component_data);

    // Filter the `included` section of the response based on the data ids that
    // are related to requested content.
    $content['included'] = array_values(array_filter($content['included'], function($include) use ($filtered_data_ids) {
      return !in_array($include['id'], $filtered_data_ids);
    }));
    if (empty($content['included'])) {
      unset($content['included']);
    }

    foreach ($content['data'] as $delta => $report) {
      // Remove fields from node data that are not relationship fields.
      $content['data'][$delta]['attributes'] = array_intersect_key($report['attributes'], array_flip(['title']));
      foreach ($report['relationships'] as $field_name => $field) {
        if (isset($field['data']['id']) && in_array($field['data']['id'], $filtered_data_ids)) {
          $content['data'][$delta]['relationships'][$field_name]['data'] = [];
        }
        else if (!isset($field['data']['id'])) {
          $content['data'][$delta]['relationships'][$field_name]['data'] = array_values(array_filter($field['data'], function($component) use ($filtered_data_ids) {
            return !in_array($component['id'], $filtered_data_ids);
          }));
        }
      }

      // Remove the agency_components_to_omit from report `relationship` fields.
      // Remove component data references from report `relationship` fields.
    }


    $response = $response->setContent(json_encode($content));
    $event->setResponse($response);
  }

}
