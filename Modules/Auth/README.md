# Auth module

Owns XFlickr session identity and local user lifecycle: login, logout, registration, activation, password reset, and related CLI commands. It does not own Flickr or storage OAuth.

HTTP follows Controller → FormRequest → Service → Repository → Model. See `docs/00-architecture/modules.md`.
