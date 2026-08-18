# Project authorization rules

- Always implement application roles and permissions with `spatie/laravel-permission`.
- Use Spatie roles, permissions, middleware, and Laravel Gate integration instead of adding boolean role columns to `users` or building a parallel authorization system.
- The canonical unrestricted application role is `super-admin` on the `web` guard.
- Keep WorkOS authentication enabled only in the `production` environment. Local and test environments use the local email/password authentication flow.

# Container execution rules

- Always execute project commands inside their Docker containers; do not run project PHP, Artisan, Composer, Node, npm, Vite, lint, type-check, build, migration, seeder, or test commands directly on the host.
- Use the `chags-app` container for application commands unless `docker-compose.yml` defines a more specific service for the task.
- Keep generated files and runtime cache files owned and writable by the container user. Do not work around permission errors by running the same generator on the host.
- Diagnose container availability with `docker ps` and execute commands with `docker exec chags-app ...`.
