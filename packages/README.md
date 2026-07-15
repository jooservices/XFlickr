# JOOservices React packages

Packed `@jooservices/react-*` **v1.0.0** tarballs from [jooservices/react](https://github.com/jooservices/react).

npm’s `github:…#v1.0.0&path:components/…` form does not resolve subdirectories reliably, so XFlickr vendors these `*.tgz` files (same pattern as earlier 0.1.x packs).

## Refresh from a local react clone

```bash
# from jooservices/react @ v1.0.0 (dist/ committed)
for pkg in ui config layout table content action-buttons card modal toast; do
  (cd "components/$pkg" && npm pack --pack-destination /path/to/XFlickr/packages)
done
# then bump package.json file:packages/…-1.0.0.tgz and npm install
```
