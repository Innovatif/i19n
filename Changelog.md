# Changelog

## 1.0.1
- bug fix: detect field as CMS

## 0.9.20
- php 8 deprecation warning fix
- added php requirement
- critical path fix for windows environment

## 0.9.19
- add Created column

## 0.9.18
- improve backend entities filter to recognize SINGULAR and PLURAL entities

## 0.9.17
- separated functionality of collecting backend / frontend entities
- minimized default locales to the one set as default and introduced yml setting to override
- fixed bug that ignored default modules being set to themes and app
- removed collection of backend entities when triggering frontend search (duplicate)
- fixed filtering when NO is selected for backend entities
- improved search on entities with namespace ((double)backslash problem)
- collection on backend entities can be limited to modules now
- changed the "engine" of backend entities collection to i19nWritter

## 0.9.16
- fix some bugs with types
- different CSV export

## 0.9.15
- ability to show i18n entities on frontend
- export to CSV

## 0.9.14
- Added task for clearing unused variables in database
- Changed namespace for Tasks from "Innovatif\i19n" to "Innovatif\i19n\Task"
- PSR formatted code

## 0.9.13
- Added option to enable/disable clear cache task (default: disabled, enable for multi-server environment)
