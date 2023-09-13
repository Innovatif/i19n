# Changelog


## 4.1.19
- add Created column

## 4.1.18
- improve backend entities filter to recognize SINGULAR and PLURAL entities

## 4.1.17
- separated functionality of collecting backend / frontend entities
- minimized default locales to the one set as default and introduced yml setting to override
- fixed bug that ignored default modules being set to themes and app
- removed collection of backend entities when triggering frontend search (duplicate)
- fixed filtering when NO is selected for backend entities
- improved search on entities with namespace ((double)backslash problem)
- collection on backend entities can be limited to modules now
- changed the "engine" of backend entities collection to i19nWritter

## 4.1.16
- fix some bugs with types
- different CSV export

## 4.1.15
- ability to show i18n entities on frontend
- export to CSV

## 4.1.14
- Added task for clearing unused variables in database
- Changed namespace for Tasks from "Innovatif\i19n" to "Innovatif\i19n\Task"
- PSR formatted code

## 4.1.13
- Added option to enable/disable clear cache task (default: disabled, enable for multi-server environment)
