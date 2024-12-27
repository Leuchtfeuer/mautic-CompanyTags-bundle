# Plugin: Company Tags by Leuchtfeuer



## Overview

This plugin brings company-based Tagging to Mautic.

It is part of the "ABM" suite of plugins that extends Mautic capabilities for working with Companies.

## Requirements
- Mautic 5.x (minimum 5.1)
- PHP 8.1 or higher

## Installation
### Composer
This plugin can be installed through composer.

### Manual install
Alternatively, it can be installed manually, following the usual steps:

* Download the plugin
* Unzip to the Mautic `plugins` directory
* Rename folder to `LeuchtfeuerCompanyTagsBundle` 

-
* In the Mautic backend, go to the `Plugins` page as an administrator
* Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

* If you have shell access, execute `php bin\console cache:clear` and `php bin\console mautic:plugins:reload` to install the plugins.

## Plugin Activation and Configuration
1. Go to `Plugins` page
2. Click on the `Company Tags` plugin
3. ENABLE the plugin

## Usage
The plugin brings a new menu item `Companies -> Company Tags`. Here you can create and manage your Company Tags, and see the number of Companies are in each Tags. When you click on that number, you go to a pre-filtered Company list view.

You can also filter manually for Company Tags in the Company list view. Just enter `tag:<company tag alias name>` in the filter.

In the Company single view, you will now find a green bubble for each of the company's tag, on the right hand side. Click the bubble to remove the tag from the company with one click.

In the Company edit view, you can add or remove tags as desired.

In Campaigns, you now have a new Action called "Modify Company Tags".

In Reports, you now have a new data source "Company Tags" that allows you to filter on Company Tags.

API is also supported. You can add, remove or update company tags from companies via API.


## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.

If things are still funny, please try

`php bin/console cache:clear`

and 

`php bin/console mautic:assets:generate`

## Known Issues
* In the Company Tahgs view, bulk delete is currently not working

## Future Ideas
* Add single and bulk action for modifiying Company Tags to Company list view

## Credits
* @lenonleite
* @ekkeguembel
* @JonasLudwig1998

## Author and Contact
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.

For all other things, please email mautic-plugins@Leuchtfeuer.com
