# PDO Userspace Driver for Oracle (oci8)

###PDO via Oci8

The [alfmel/pdo-via-oci8](https://github.com/alfmel/pdo-via-oci8) package is a simple userspace driver for PDO that uses the tried and tested
[OCI8](http://php.net/oci8) functions instead of using the still experimental and not all that functional
[PDO_OCI](http://www.php.net/manual/en/ref.pdo-oci.php) library.

This fork from [yajra/laravel-pdo-via-oci8](https://github.com/yajra/laravel-pdo-via-oci8) restores full DSN support (previously removed to
fit better within Laravel) making the project fully portable to any installation. This fork also includes improved class names and error
handling for strict error reporting configurations.

**Please report any bugs you may find.**

- [Installation](#installation)
- [Usage](#usage)
- [Credits](#credits)

###Installation

Add `alfmel/pdo-via-oci8` as a requirement to composer.json:

```json
{
    "require": {
        "alfmel/pdo-via-oci8": "*"
    }
}
```
And then run `composer update`

***Note:***
lastInsertId function returns the current value of the sequence related to the table where record is inserted.
The sequence name should follow this format ```{$table}.'_'.{$column}.'_seq'``` for it to work properly.

###Usage

Using the userspace PDO driver is easy. Simply instantiate the alfmel\OCI8\PDO class instead of the PDO class in PHP. Then use
the PDO object as you would any other PDO object:

```php
<?php
$pdo = new alfmel\OCI8\PDO($dsn, $username, $password);
$statement = $pdo->prepare("SELECT * FROM MY_TABLE");
$statement->execute();
$result_set = $statement->fetchAll(PDO::FETCH_ASSOC);
```

###Credits

Special thanks to the two projects that started it all:

- [crazycodr/pdo-via-oci8](https://github.com/crazycodr/pdo-via-oci8)
- [yajra/laravel-pdo-via-oci8](https://github.com/yajra/laravel-pdo-via-oci8)

And thanks to all the contributors to these projects. Your work is truly appreciated!
