# Mocking external services

Use an existing mock module when one exists. Write a new one only when none
does, in the same pattern as the closest existing mock. The four patterns in
use:

| Pattern | When | Example |
|---|---|---|
| Guzzle middleware (service tagged `http_client_middleware`) | The code calls a URL through Drupal's HTTP client | `ewcms_content_mock` (`GiscoMiddleware`, `AvPortalThumbnailMiddleware`), `oe_media_oembed_mock`, `oe_translation_epoetry_mock` |
| `http_request_mock` ServiceMock plugin | The project already uses `drupal/http_request_mock` | `oe_search_mock` (`EuropaSearchServer`, responses in `responses/json/`), `oe_newsroom_newsletter_mock` |
| Service decorator (`decorates:` in the mock's `services.yml`) | The code talks to the service through a Drupal service | `ewcms_user_manual_links_mock`, `oe_theme_webtools_mock` |
| Event subscriber or config overrider | The module dispatches an event or reads endpoints from config | `ewcms_content_mock` (`RelatedTopicsSubscriber`), `oe_search_mock` (`config.factory.override`) |

## A middleware mock

```yaml
services:
  ewcms_content_mock.gisco_middleware:
    class: Drupal\ewcms_content_mock\GiscoMiddleware
    arguments: ['@state']
    tags:
      - { name: http_client_middleware }
```

The middleware answers only the requests it knows, with a
`FulfilledPromise` holding a `Response`, and passes every other request on
to `$handler($request, $options)`.

## Rules

- Match the request on host and path, and answer with a fixture shaped like
  the real response. Keep large responses in a fixture file inside the mock
  module, not inline.
- Let the test choose the response through state when it needs more than
  one, and read the requests the mock received from state when the test
  checks what was sent.
- Enable a new mock module where CI enables the others: both `drush en`
  lines in `.opts.yml` (`script_site_install` and `script_release_update`)
  and `runner.yml.dist` (`drupal.post_install`) on a site, the test's
  `$modules` in a component.
- In Unit tests, mock collaborators with `createMock()` (or Prophecy where
  the test class already uses it). Mock only what the code under test calls.
