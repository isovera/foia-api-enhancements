@annual_foia_report_data
Feature: Annual FOIA Report Data Feature
  In order to create Annual FOIA Reports
  As an Administrator
  I should be able to create and edit an Annual FOIA Report Data entity.

  Background:
    Given agency terms:
      | name                    | field_agency_abbreviation | description |format    | language |
      | Federal Testing Agency  | FTA                       | description |plain_text| en       |
    Given agency_component content:
      | title                   | field_agency              |
      | Test Agency Component 1 | Federal Testing Agency    |

  @api
  Scenario: Create an Annual FOIA Report Data node.
    Given I am logged in as a user with the 'Administrator' role
    And I am at 'node/add/annual_foia_report_data'
    And for 'Agency' I enter 'Federal Testing Agency'
    And for 'FOIA Annual Report Year' I enter '2019'
    And for 'Date Prepared' I enter '08/22/2019'
    When I press the 'Save' button
    Then I should see the following success messages:
      | FTA - 2019 - Annual FOIA Report has been created. |
