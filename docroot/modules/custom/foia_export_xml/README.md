INTRODUCTION
------------

The main task of the FOIA Export XML module is to transform an Annual FOIA
Report Data node into an XML file that conforms to the FOIA Annual Report
extension of the NIEM IEPD schema.  A representation of this extension schema
can be found documented on [github](https://github.com/usdoj/foia-api/blob/develop/docs/FoiaAnnualReportExtensions.xsd).

#### Exporting Annual FOIA Report Data nodes

The module defines a route with the pattern `/node/[nid]/xml` and a menu link
on node pages to that route.  This route builds the XML from node data, sets
the export as the contents of the response, sets content headers to send the
response as an attachment for download as a file titled `annual-report.xml`.
To access this route, go to an annual report node at `/node/[nid]` and click
the tab titled `XML`.

#### The export class 

The bulk of the work in this module is done in the class 
`\Drupal\foia_export_xml\ExportXml`.  The constructor of the `ExportXml` class
creates a new `\DOMDocument`, then proceeds to add each section of the
annual report to the document, conforming to the FOIA Annual Report schema
extension.  The `__toString()` method is used to convert that
`DOMDocument` to a string.  In this method, a new export can be created,
 then cast to a string as the body of the response object.



REQUIREMENTS
------------

This module depends on the annual_foia_report_data content type.

INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules#s-step-2-enable-the-module
for further information.

CONFIGURATION
-------------

Any user with permission to view an Annual FOIA Report Data node, also has
permission to export that node to XML.  Access depends on whether or not the 
node is published.

Configure the user permissions in Administration » People » Permissions:

* View published content
* View unpublished content
