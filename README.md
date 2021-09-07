# Anonymous Timezone

## Installation

 - `composer require 'drupal/anonymous_timezone:1.0.x-dev@dev'`
 - `drush en anonymous_timezone`
 - Visit `/admin/config/anonymous_timezone` and specify the path of the GeoDB file.

## Usage

The existing custom code and contrib modules should work out of the box, as they are already
supposed to not override what Drupal core does, to render everything according to the local timezone.
Beware that render arrays are still cached, so `timezone` context is a must to make sure that
the render arrays are cached differently for each timezone.

## Known issues
 - The module disables page cache generally, it leads to degraded performance for anonymous users.
 - The accuracy of the timezone detection is limited by the GeoIP database.
