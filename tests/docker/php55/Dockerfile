FROM debian:bookworm-slim

ARG PHP_VERSION=5.5.38

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gcc \
        make \
        libc6-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL \
      "https://museum.php.net/php5/php-${PHP_VERSION}.tar.gz" \
      -o /tmp/php.tar.gz \
    && mkdir /tmp/php-src \
    && tar -xzf /tmp/php.tar.gz -C /tmp/php-src --strip-components=1 \
    && cd /tmp/php-src \
    && ./configure \
        --disable-all \
        --enable-cli \
        --enable-json \
    && make -j"$(nproc)" \
    && make install \
    && rm -rf /tmp/php-src /tmp/php.tar.gz

WORKDIR /app

CMD ["php", "-v"]
