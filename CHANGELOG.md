# Changelog

## 5.0.0

### Changed

- Chart.js upgraded from 3.9.1 to 4.6.1. It is loaded once per page instead of once per element.
- The chart configuration is passed as JSON. Titles containing quotes no longer break the chart.
- All texts are translated (de/en), including the default titles of new elements.

### Fixed

- Charts are redrawn after copy, cut and paste in the page editor.
- Incomplete settings or a deleted survey show a hint instead of an error page.
- Missing levels, empty data and divisions by zero no longer cause errors.
- Survey selection errors are translated.
- Competences without levels are rejected.
- Fewer database queries on pages with several elements.
