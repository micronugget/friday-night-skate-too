# fridaynightskate Theme — Coding Patterns
> Use when writing or modifying files in `web/themes/custom/fridaynightskate/` —
> Twig templates, SCSS, SDC components, JS behaviors, preprocess functions, and library definitions.
> Covers the build pipeline, the Starry Night design system, and Masonry/Swiper integration.

## Build Pipeline

The theme uses **Laravel Mix (webpack.mix.js)** with Sass. **Never edit files
in `build/` directly** — they are generated output.

> **Known issue — NVM:** `ddev npm run dev` (from project root) fails because the
> root `package.json` has no `dev` script. Always build from inside the theme
> directory.

```bash
# Compile once (cd into theme inside DDEV)
ddev exec "cd web/themes/custom/fridaynightskate && npm run dev" 2>&1 | tail -20

# Watch mode
ddev exec "cd web/themes/custom/fridaynightskate && npm run watch"
```

After editing any `.scss` or component `.js` file, run the compile command above and
**also run `ddev drush cr`** — Drupal caches the SDC asset manifest and library
definitions.

### What compiles what

| Source | Output |
|--------|--------|
| `src/scss/main.style.scss` | `build/css/main.style.css` |
| `src/js/main.script.js` | `build/js/main.script.js` |
| `components/**/*.scss` | `components/**/*.css` (same path, `.css` extension) |
| `components/**/_*.js` (underscore-prefixed) | `components/**/*.js` (underscore stripped) |

SDC component CSS is compiled from `components/<name>/<name>.scss` to
`components/<name>/<name>.css` by the glob loop in `webpack.mix.js`. The `.css`
file is what Drupal reads from the SDC manifest — never import component SCSS
from `main.style.scss`.

---

## Library Definitions (`fridaynightskate.libraries.yml`)

When adding a new JS file, define a library entry before attaching it:

```yaml
my-feature:
  js:
    src/js/my-feature.js: {}
  dependencies:
    - core/drupal
    - core/drupalSettings   # only if you read drupalSettings in JS
    - core/jquery           # only if you use $()
    - core/once             # required when using once()
```

Attach libraries from a module's `hook_form_alter()` or preprocess function:

```php
$form['#attached']['library'][] = 'fridaynightskate/my-feature';
```

---

## Twig Templates

- Templates live in `templates/` following Drupal's suggestion system.
- Use `{{ dump() }}` only during development — remove before committing.
- Preprocess variables are added in `fridaynightskate.theme` (or `includes/*.theme`).

### Preprocess pattern

```php
// fridaynightskate.theme or includes/myarea.theme
function fridaynightskate_preprocess_node__archive_media(&$variables) {
  $node = $variables['node'];
  $variables['my_var'] = $node->get('field_something')->value;
}
```

---

## SDC Components

Components live in `components/<name>/`. Each component needs:
- `<name>.component.yml` — metadata and props schema
- `<name>.twig` — template
- `<name>.scss` — styles (compiled to `<name>.css` by webpack)
- `_<name>.js` (optional, underscore-prefixed) — compiled to `<name>.js`

After adding or modifying a component, always rebuild assets and clear caches:

```bash
ddev exec "cd web/themes/custom/fridaynightskate && npm run dev" 2>&1 | tail -20
ddev drush cr
```

---

## JS Behaviors

All JS must use Drupal behaviors:

```js
(function (Drupal, once) {
  Drupal.behaviors.myFeature = {
    attach(context) {
      once('my-feature', '.my-selector', context).forEach(function (el) {
        // your code
      });
    },
  };
})(Drupal, once);
```

---

## Design Tokens — Starry Night Edition

The theme is inspired by Van Gogh's *Starry Night*. Key SCSS variables:

- `$night-sky: #0d1b2a` — deep navy background
- `$starry-gold: #f5c842` — golden accent
- `$swirl-blue: #1a4a8a` — swirling mid-blue
- `$moonlight: #e8f4f8` — soft white/light text
- `$font-display: 'Bebas Neue'` (or equivalent display font)

Always use SCSS variables and CSS custom properties — never hardcode hex values in templates or inline styles.

---

## Masonry & Swiper

The archive grid uses **Masonry** for layout and **Swiper** for the modal viewer carousel.

- Masonry is initialised in `src/js/main.script.js` (or a dedicated behavior file).
- Swiper is initialised per-modal — attach only when the modal opens to avoid layout thrash.
- Both libraries are declared in `fridaynightskate.libraries.yml` and loaded via the `style` library or a dedicated library entry.

After any JS change: rebuild assets, then `ddev drush cr`.

---

## Canvas Compatibility

Canvas is the page-building layer; `fridaynightskate` is the visual layer. They are complementary.

- Keep Mercury installed — Canvas SDC components may reference it internally.
- Do not override Canvas layout templates unless strictly necessary.
- When adding theme-level CSS that affects Canvas regions, test in the Canvas editor as well as the frontend.

---

## Key Files

```
web/themes/custom/fridaynightskate/
├── fridaynightskate.info.yml
├── fridaynightskate.libraries.yml
├── fridaynightskate.theme
├── webpack.mix.js
├── src/
│   ├── scss/main.style.scss
│   └── js/main.script.js
├── build/
│   ├── css/main.style.css   ← generated, do not edit
│   └── js/main.script.js    ← generated, do not edit
├── components/
│   └── <name>/
│       ├── <name>.component.yml
│       ├── <name>.twig
│       ├── <name>.scss
│       └── _<name>.js
├── includes/
└── templates/
```
