=== WP API SwaggerUI ===
Contributors: agussuroyo
Donate link: https://www.paypal.me/agussuroyo
Tags: swaggerui, wp swaggerui, wp rest api, wp swagger rest api, swaggerui rest api, swagger rest api, wp swagger, api, swagger, rest api
Requires at least: 4.7
Tested up to: 5.9
Stable tag: 1.2.0
Requires PHP: 5.4
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

WordPress REST API with Swagger UI.

== Description ==

SwaggerUI used to make WordPress REST API endpoint have a interactive UI, so we can check our API endpoint directly from the website it self

Feature:

* Support for GET, POST, PUT, PATCH and DELETE request method
* Support for Auth Basic authorization method
* Choose which namespace API that will be used on the SwaggerUI

== Installation ==

This plugin can be installed directly from your site.

1. Log in and navigate to Plugins > Add New.
2. Type “WP API SwaggerUI” into the Search and hit Enter.
3. Locate the WP API SwaggerUI plugin in the list of search results and click Install Now.
4. Once installed, click the Activate link.

== Screenshots ==

1. SwaggerUI Interface
2. Options to choose namespace Rest API

== Changelog ==

= 1.2.0 =
* Update doc
* Force object type to string
* Node modules update

= 1.1.2 =
* Update regex for parameter detection

= 1.1.1 =
* Put back missing header element

= 1.1.0  =
* Use swagger-ui npm version
* Auto tags on endpoint

= 1.0.9 =
* Fix readme typo

= 1.0.8 =
* Restore custom port support

= 1.0.7 =
* Support `produces` and `consumes` directly via register_rest_route 3rd parameter

= 1.0.6 =
* Change site_url to home_url

= 1.0.5 =
* Support summary and desription on each endpoint api

= 1.0.4 =
* make WooCommerce REST API Key works on Swagger Docs Auth

= 1.0.3 =
* change template_include priority
* change dtermine_current_user priority

= 1.0.2 =
* Ensure REDIRECT_HTTP_AUTHORIZATION is not empty

= 1.0.1 =
* Auto add params from path

= 1.0 =
* Initial release