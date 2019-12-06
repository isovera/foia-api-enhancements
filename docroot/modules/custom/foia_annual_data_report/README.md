CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------

The FOIA Annual Data Report module is a utility module that implements form 
tweaks for the Annual Data Report add/edit form and custom configuration, 
including Annual Report Memory Limit, that aid in editing to the annual report 
data.


REQUIREMENTS
------------

FOIA Annual Data Report has no contrib or custom module requirements.
It does have library dependencies on:
 * core/jquery
 * core/drupalSettings


INSTALLATION
------------

FOIA Annual Report Data is a custom Drupal module so unlike contrib modules,
the codebase is not installed via composer. Enable as you would normally
enable a contributed Drupal module. Visit
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules#s-step-2-enable-the-module
for further information.


CONFIGURATION
-------------

No configuration required for the node edit form updates.

To configure the Annual Report Memory limit go to:
/admin/config/system/foia_annual_data_report_memory_limit

Fill in the `Annual Report Memory Limit` field  if you wish to override the 
default PHP Memory Limit set for the platform. For debugging, check the 
`Debug Annual Report memory limit.` checkbox.
