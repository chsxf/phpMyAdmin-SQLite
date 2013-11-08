phpMyAdmin-SQLite
=================

phpMyAdmin-SQLite is an export plugin for phpMyAdmin designed to generate database dumps or binary files compatible with SQLite 3.
The project is currently in an early alpha state but should be ready for testing purposes.

Compatibility
-------------

At this time, phpMyAdmin-SQLite has only been tested against phpMyAdmin 3.5.2.2.
However, the plugin should work for phpMyAdmin 3.4 and later versions of the 3.x branch.

**This plugin is not compatible yet with phpMyAdmin 4.0 and later versions.**

Installation
------------

Copy the content of src/export folder to your phpMyAdmin's libraries/export folder.
That's all!

Configuration
-------------

You should add to your phpMyAdmin config.inc.php the following line in order to configure the default behavior for structure and/or data export :

	$cfg['Export']['sqlite3_structure_or_data'] = 'structure_and_data';
	$cfg['Export']['sqlite3-binary_structure_or_data'] = 'structure_and_data';

Supported values are `structure` for exporting structure only, `data` for exporting data only and  `structure_and_data` for exporting both.

License
-------

phpMyAdmin-SQLite source code is released under the terms of the GNU General Public License v2 if not specified otherwise.
