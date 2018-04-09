<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 9/4/2561
 * Time: 11:26 น.
 */

namespace Model;


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Photo extends DataObject implements TestOnly
{
    private static $db = array(
        'Title' => 'Varchar(255)',
        'Description' => 'HTMLText',
        'TakenAt' => 'Datetime',

        'GeoIsPublic' => 'Boolean',

        // these should come from mappable but it's not 4.x ready
        'Lat' => 'Decimal(18,15)',
        'Lon' => 'Decimal(18,15)',

        'FlickrPlaceID' => 'Varchar(255)',

        'Aperture' => 'Float',
        'ShutterSpeed' => 'Varchar',
        'FocalLength35mm' => 'Int',
        'ISO' => 'Int',
    );
}
