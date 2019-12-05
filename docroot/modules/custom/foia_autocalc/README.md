INTRODUCTION
------------
The FOIA Autocalc module allows administrators to configure a field to
automatically calculate its value as the sum of one or more fields existing
on the same node.

## Calculations

Calculations are done in javascript on the following events:

* Page load: When the add or edit form is first loaded and behaviors are
attached, the module will attempt to calculate the value of any auto
calculated field.
* Change: When a field that is an addend for any auto-calculated field is
changed, the calculations will be run again, summing any fields that are
dependent on the changed field.

RECOMMENDED MODULES
-------------------

* Field UI: When enabled, auto-calculation settings can be configured on field
forms.

INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules#s-step-2-enable-the-module
for further information.

CONFIGURATION
-------------

In order to configure autocalculated fields, users must have permission to
administer fields for a given entity. Configure the following or similar user
permissions in Administration » People » Permissions:

* Content: Administer fields
* Paragraph: Administer fields

To configure a field as autocalculated, edit or create a field on an entity.
At the bottom of the field form, there will be a section titled
"Automatically Calculated Value" where one or more fields can be configured as
addends for calculating the value of the field being configured.  The parts
of the configuration are:

* Field: The machine name of a field that should be summed to create the
current field's value.
* This entity: A checkbox indicating whether the calculation should only use
this field value if it exists on the same entity as the field being
configured.  For example, if the field being calculated is attached to a
paragraph item and the addend field's "This entity" checkbox is checked, the
auto-calculation will only use the addend field value that exists on the same
paragraph item as the calculated field.
