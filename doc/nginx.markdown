# nginx Configuration

Rewrite rules for **Archive My Tweets**, converted to [nginx](http://nginx.org/) syntax:

    location / {
        try_files $uri $uri/ index.php;
    }

    location ~ ^/([0-9]+)/?$ {
        rewrite ^/([0-9]+)/?$ /index.php?id=$1;
    }

    location ~ "^/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/?$" {
        rewrite "^/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/?$" /index.php?year=$1&month=$2&day=$3;
    }

    location ~ "^/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/page/([0-9]+)/?$" {
        rewrite "^/archive/([0-9]{4})/([0-9]{2})/([0-9]{2})/page/([0-9]+)/?$" /index.php?year=$1&month=$2&day=$3&page=$4;
    }

    location ~ "^/archive/([0-9]{4})/([0-9]{2})/?$" {
        rewrite "^/archive/([0-9]{4})/([0-9]{2})/?$" /index.php?year=$1&month=$2;
    }

    location ~ "^/archive/([0-9]{4})/([0-9]{2})/page/([0-9]+)/?$" {
        rewrite "^/archive/([0-9]{4})/([0-9]{2})/page/([0-9]+)/?$" /index.php?year=$1&month=$2&page=$3;
    }

    location ~ "^/archive/([0-9]{4})/?$" {
        rewrite "^/archive/([0-9]{4})/?$" /index.php?year=$1;
    }

    location ~ "^/archive/([0-9]{4})/page/([0-9]+)/?$" {
        rewrite "^/archive/([0-9]{4})/page/([0-9]+)/?$" /index.php?year=$1&page=$2;
    }

    location ~ "^/client/(.*)/$" {
        rewrite "^/client/(.*)/$" /index.php?client=$1;
    }

    location ~ "^/client/(.*)/page/([0-9]+)/?$" {
        rewrite "^/client/(.*)/page/([0-9]+)/?$" /index.php?client=$1&page=2;
    }

    location ~ "^/page/([0-9]+)/?$" {
        rewrite "^/page/([0-9]+)/?$" /index.php?page=$1;
    }

    location ~ "^/favorites/?$" {
        rewrite "^/favorites/?$" /index.php?favorites=$1;
    }

    location ~ "^/favorites/page/([0-9]+)/?$" {
        rewrite "^/favorites/page/([0-9]+)/?$" /index.php?favorites=1&page=$2;
    }

    location ~ "^/stats/?$" {
        rewrite "^/stats/?$" /index.php?method=stats;
    }
