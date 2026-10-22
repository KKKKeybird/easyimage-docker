ARG PHP_IMAGE=php:8.4-fpm-bookworm
FROM ${PHP_IMAGE}

ARG EASYIMAGE_VERSION=2.8.6
ARG EASYIMAGE_SHA256=eedf291fe6495acfa01b97640eff6fcd81b053152314ff283162b00299c4d519
ARG SOURCE_REPOSITORY=https://github.com/KKKKeybird/easyimage-docker
LABEL org.opencontainers.image.source=${SOURCE_REPOSITORY} \
      org.opencontainers.image.title="EasyImage on maintained PHP" \
      org.opencontainers.image.version=${EASYIMAGE_VERSION} \
      org.opencontainers.image.licenses="GPL-2.0-only"

RUN set -eux; \
    php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4 || version_compare(PHP_VERSION, "8.4.27", "<")) exit(1);'; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx supervisor curl ca-certificates \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev libonig-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd mbstring zip exif bcmath opcache; \
    rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default; \
    mkdir -p /app/web /opt/easyimage-defaults /run/php /var/log/supervisor; \
    curl -fsSL --retry 3 "https://codeload.github.com/icret/EasyImages2.0/tar.gz/refs/tags/${EASYIMAGE_VERSION}" -o /tmp/app.tar.gz; \
    echo "${EASYIMAGE_SHA256}  /tmp/app.tar.gz" | sha256sum -c -; \
    tar -xzf /tmp/app.tar.gz -C /app/web --strip-components=1; \
    rm /tmp/app.tar.gz; \
    cp -a /app/web/config /app/web/i /opt/easyimage-defaults/; \
    printf '<?php\n$tokenList = [];\n' > /opt/easyimage-defaults/config/api_key.php; \
    chown -R www-data:www-data /app/web

COPY docker/nginx.conf /etc/nginx/conf.d/easyimage.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-easyimage.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-easyimage.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/easyimage.conf
COPY docker/health.php /opt/easyimage-health.php
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/easyimage-entrypoint

ENV TZ=Asia/Shanghai PUID=1000 PGID=1000 DEBUG=false
WORKDIR /app/web
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/healthz >/dev/null || exit 1
ENTRYPOINT ["easyimage-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/easyimage.conf"]
