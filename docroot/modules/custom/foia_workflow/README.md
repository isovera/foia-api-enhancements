CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------

The FOIA Workflow module adds custom access requirement to Annual FOIA Report
Data nodes based on a user's agency and role, and the workflow state of the
node.


### Additional access restrictions

 * Users with the role Agency Administrator are not allowed to edit a report
  that is authored by another user if it is still in a `draft` state.
 * Users with the role Agency Manager are not allowed to edit or delete a report
  that is not in either the `draft` or `back_with_agency` state.


### Additional access granted

 * Users with the role Agency Manager are a allowed to edit or delete a
  report that has been authored by a user in their agency, if the report is
  in the `draft` or `back_with_agency` state.




INSTALLATION
------------

FOIA Workflow is a custom Drupal module so unlike contrib modules, the codebase
is not installed via composer. Enable as you would normally enable a
contributed Drupal module. Visit
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules#s-step-2-enable-the-module
for futher information.


CONFIGURATION
-------------

