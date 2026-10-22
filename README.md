# EasyImage Docker with maintained PHP

EasyImages **2.8.6** on the official **PHP 8.4 FPM** image, Nginx and Supervisor.
Community packaging; not an official image of the EasyImages project.

```yaml
services:
  easyimage:
    image: ghcr.io/kkkkeybird/easyimage-docker:latest
    restart: unless-stopped
    ports: ["8080:80"]
    environment:
      TZ: Asia/Shanghai
      PUID: "1000"
      PGID: "1000"
    volumes:
      - ./config:/app/web/config
      - ./images:/app/web/i
```

Open `http://your-server:8080` and complete the upstream installation wizard.
Existing `ddsderek/easyimage` configuration and image mounts use the same paths.
Back up both directories before migrating. Existing configuration is not replaced
on startup; empty directories are initialized from the upstream defaults, with an
empty API token list. `DEBUG=true` enables PHP error display for troubleshooting.

## Builds and updates

- Native `linux/amd64` and `linux/arm64` builds run on every main-branch push,
  weekly, or through Actions → Run workflow.
- Each build pulls the latest `php:8.4-fpm-bookworm` base. Builds fail if PHP is
  older than **8.4.27**, which fixes CVE-2026-109618 and CVE-2026-109619.
- Before publication, both architectures pass PHP syntax checks and real HTTP
  tests covering upload, retrieval, deletion, API authentication, compression,
  text/image watermarks, private paths and upload-directory PHP blocking.
- `latest`, `2.8.6` and `php8.4` are moving tags. `build-RUN_ID` identifies a
  particular successful workflow publication. Pin the image digest for stronger
  reproducibility. Pulling an image does not update a running container:

```sh
docker compose pull
docker compose up -d
```

Application source is downloaded from the upstream `2.8.6` tag and verified with
SHA-256 `eedf291fe6495acfa01b97640eff6fcd81b053152314ff283162b00299c4d519`.
Upstream's 2.8.6 archive still contains an internal 2.8.5 display constant; the
source archive and image tag identify the packaged release.

## Security scope

This image updates the PHP runtime and web-server packaging. It does not claim
to fix all application vulnerabilities or audit the bundled JavaScript/PHP
dependencies. Upstream reports, including the file-deletion and SVG issues, must
be evaluated separately. Serve behind HTTPS, use strong credentials and review
guest-upload settings before exposing the service.

PHP execution in `/i/`, direct access to `/config/`, `/admin/logs/` and hidden files
are blocked by Nginx. Health checks exercise both Nginx and PHP-FPM. PHP file
uploads use the existing 512 MiB runtime limit; application settings can impose a
lower limit. TLS termination remains the responsibility of the reverse proxy.

## Source and license

- Application: [icret/EasyImages2.0](https://github.com/icret/EasyImages2.0)
- Original image that inspired the compatible mount layout:
  [DDSRem-Dev/EasyImages-Docker](https://github.com/DDSRem-Dev/EasyImages-Docker)
- [PHP security advisory](https://www.php.net/archive/2026.php)

GPL-2.0-only; upstream copyright notices are preserved. This repository contains
only packaging and synthetic tests, never a deployment's configuration or images.
