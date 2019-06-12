# sphinxsearch

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]


This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what
PSRs you support to avoid any confusion with users and contributors.

















# Notes
## Attaching
Use `docker exec -it <id> bash`.  Note that the terminal behaves a little oddly but it works.

## Indexing
```indexer --all```
Possibly a rotate in there also

## Restarting
This is not possible as it would kill the docker container


## Config Tweaks
WARNING: key 'mva_updates_pool' was permanently removed from configuration. Refer to documentation for details.
WARNING: key 'docinfo' was permanently removed from configuration. Refer to documentation for details.
WARNING: key 'mlock' is deprecated in /etc/sphinxsearch/sphinx.conf line 430; use 'mlock in particular access_... option' instead.
WARNING: key 'charset_type' was permanently removed from configuration. Refer to documentation for details.
WARNING: key 'enable_star' was permanently removed from configuration. Refer to documentation for details.


#TODO
* Postgres / MySQL, avoid hardwiring


untweaked query

SELECT DISTINCT "SiteTree_Live"."ClassName", "SiteTree_Live"."LastEdited", "SiteTree_Live"."Created", "SiteTree_Live"."Title", "SiteTree_Live"."MenuTitle", "SiteTree_Live"."Content", "SiteTree_Live"."Sort", "SiteTree_Live"."ParentID", "SiteTree_Live"."ID", 
			CASE WHEN "SiteTree_Live"."ClassName" IS NOT NULL THEN "SiteTree_Live"."ClassName"
			ELSE  E'SilverStripe\\CMS\\Model\\SiteTree' END AS "RecordClassName"
 FROM "SiteTree_Live"





























## Structure

If any of the following are applicable to your project, then the directory structure should follow industry best practices by being named the following.

```
bin/        
config/
src/
tests/
vendor/
```


## Install

Via Composer

``` bash
$ composer require suilven/sphinxsearch
```

## Usage

``` php
$skeleton = new Suilven\SphinxSearch();
echo $skeleton->echoPhrase('Hello, League!');
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email gordon.b.anderson@gmail.com instead of using the issue tracker.

## Credits

- [Gordon Anderson][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/suilven/sphinxsearch.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/suilven/sphinxsearch/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/suilven/sphinxsearch.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/suilven/sphinxsearch.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/suilven/sphinxsearch.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/suilven/sphinxsearch
[link-travis]: https://travis-ci.org/suilven/sphinxsearch
[link-scrutinizer]: https://scrutinizer-ci.com/g/suilven/sphinxsearch/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/suilven/sphinxsearch
[link-downloads]: https://packagist.org/packages/suilven/sphinxsearch
[link-author]: https://github.com/gordonbanderson
[link-contributors]: ../../contributors
