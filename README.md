# DaData Integration

## Overview
The **DaData Integration** module provides integration with the [DaData Suggestions API](https://dadata.ru/api/suggest/) for autocomplete functionality in Drupal forms.  
It allows you to attach DaData suggestions (address, city, full name, company, email) to any text fields on your site.

This module is useful for:
- Autocomplete for addresses (with granular control by country, region, city, street, etc.).
- Autocomplete for companies (party).
- Autocomplete for personal names (fio).
- Autocomplete for emails.

---

## Requirements
- Drupal 10 or 11
- A valid DaData API key (get one at [dadata.ru](https://dadata.ru/))

---

## Installation
1. Download and install the module as usual:
  ```bash
  composer require drupal/dadata_integration
  drush en dadata_integration
  ```
2.	Obtain an API key from your DaData account
3.	Navigate to:
  ```
  Administration » Configuration » Web services » DaData Integration (/admin/config/services/dadata).
  ```
4.	Enter your API key and (optionally) override the base API URL.

## Configuration
The configuration form lets you:
- Set the API Key and Base API URL.
- Define which form fields should use DaData suggestions.
- Choose the suggestion type:
- address — Address autocomplete
- fio — Full name
- email — Email
- party — Company

For address suggestions you can also configure the granularity (bound):
- country
- region
- city
- settlement
- street
- house

Each field is defined by its HTML ID (for example: `edit-city`).

## Usage
Once configured, the module automatically attaches a JavaScript behavior to the selected fields.
When the user types in the field, suggestions from DaData are displayed in a dropdown.
Works with any field types and Webform elements.

## Translations
The module ships with:
- dadata_integration.pot — template for translators.
- translations/ru.po — Russian translation.

To contribute new translations, copy dadata_integration.pot to your language code (e.g., fr.po) and provide translations.

## Maintainers
- [coderteam](https://coderteam.ru)
- [antiden](https://www.drupal.org/u/antiden)
