![AMIGA](https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/Amiga_Logo_1985.svg/800px-Amiga_Logo_1985.svg.png)

# Amiga
Amiga is a PHP library that enable oldschool music files reading with PHP


## Getting Started

### Installation

Amiga requires PHP >= 7.4.

```shell
composer require jambonbill/amiga
```

### Supported file formats
- ahx
- protracker mod

### Documentation

Full documentation can be found over on ... later

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

// create a Amiga\Protracker instance
$PT=new Amiga\Protracker();
// load protracker module
$PT->open('empty.mod');
// do something with it

```
