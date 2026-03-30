#!/usr/bin/env bash
# StarHosting Installer
# Supports: Ubuntu 22.04, 24.04 / Debian 11, 12
# Run as root:
#   curl -fsSL https://raw.githubusercontent.com/sanona-ltd/starhosting/main/install.sh -o /tmp/starhosting-install.sh && sudo bash /tmp/starhosting-install.sh

set -euo pipefail

# ─────────────────────────────────────────────────────────────
# Colours & helpers
# ─────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; exit 1; }

banner() {
    echo -e "${BOLD}${CYAN}"
    echo "  ╔═══════════════════════════════════════════╗"
    echo "  ║          ★  StarHosting Installer  ★      ║"
    echo "  ║       Modern Web Hosting Control Panel     ║"
    echo "  ╚═══════════════════════════════════════════╝"
    echo -e "${RESET}"
}

# ─────────────────────────────────────────────────────────────
# Pre-flight checks
# ─────────────────────────────────────────────────────────────
check_root() {
    if [[ $EUID -ne 0 ]]; then
        error "This installer must be run as root. Try: sudo bash install.sh"
    fi
}

check_os() {
    if [[ ! -f /etc/os-release ]]; then
        error "Cannot detect OS. /etc/os-release not found."
    fi
    # shellcheck source=/dev/null
    source /etc/os-release

    local supported=0
    case "$ID" in
        ubuntu)
            case "$VERSION_ID" in
                22.04|24.04) supported=1 ;;
            esac
            ;;
        debian)
            case "$VERSION_ID" in
                11|12) supported=1 ;;
            esac
            ;;
    esac

    if [[ $supported -eq 0 ]]; then
        error "Unsupported OS: $PRETTY_NAME. StarHosting requires Ubuntu 22.04/24.04 or Debian 11/12."
    fi

    success "OS detected: $PRETTY_NAME"
    OS_ID="$ID"
    OS_VERSION="$VERSION_ID"
}

# ─────────────────────────────────────────────────────────────
# Interactive prompts
# ─────────────────────────────────────────────────────────────
choose_webserver() {
    echo ""
    echo -e "${BOLD}Choose a Web Server:${RESET}"
    echo "  [1] Nginx       (recommended)"
    echo "  [2] Apache2"
    echo "  [3] OpenLiteSpeed"
    echo ""
    read -rp "  Enter choice [1]: " ws_choice
    ws_choice="${ws_choice:-1}"

    case "$ws_choice" in
        1) WEBSERVER="nginx" ;;
        2) WEBSERVER="apache" ;;
        3) WEBSERVER="openlitespeed" ;;
        *) warn "Invalid choice, defaulting to Nginx."; WEBSERVER="nginx" ;;
    esac
    success "Web server: $WEBSERVER"
}

choose_database() {
    echo ""
    echo -e "${BOLD}Choose a Database:${RESET}"
    echo "  [1] MariaDB     (recommended)"
    echo "  [2] MySQL"
    echo "  [3] PostgreSQL"
    echo ""
    read -rp "  Enter choice [1]: " db_choice
    db_choice="${db_choice:-1}"

    case "$db_choice" in
        1) DATABASE="mariadb" ;;
        2) DATABASE="mysql" ;;
        3) DATABASE="postgresql" ;;
        *) warn "Invalid choice, defaulting to MariaDB."; DATABASE="mariadb" ;;
    esac
    success "Database: $DATABASE"
}

choose_mail() {
    echo ""
    echo -e "${BOLD}Optional Features:${RESET}"
    read -rp "  [M] Install Mail Server (Postfix + Dovecot)? [y/N]: " mail_choice
    mail_choice="${mail_choice:-N}"

    if [[ "$mail_choice" =~ ^[Yy]$ ]]; then
        INSTALL_MAIL=true
        success "Mail server: Postfix + Dovecot will be installed"
    else
        INSTALL_MAIL=false
        info "Mail server: skipped"
    fi
}

show_summary() {
    echo ""
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo -e "${BOLD}  Installation Summary${RESET}"
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo -e "  OS:          ${CYAN}$PRETTY_NAME${RESET}"
    echo -e "  Web Server:  ${CYAN}$WEBSERVER${RESET}"
    echo -e "  Database:    ${CYAN}$DATABASE${RESET}"
    echo -e "  Mail Server: ${CYAN}$( [[ $INSTALL_MAIL == true ]] && echo 'Postfix + Dovecot' || echo 'No' )${RESET}"
    echo -e "  Panel Port:  ${CYAN}8443${RESET}"
    echo -e "  Install Dir: ${CYAN}/var/www/starhosting${RESET}"
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
    echo ""
    read -rp "  Proceed with installation? [Y/n]: " confirm
    confirm="${confirm:-Y}"
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo "Installation cancelled."
        exit 0
    fi
}

# ─────────────────────────────────────────────────────────────
# Package installation
# ─────────────────────────────────────────────────────────────
install_base_deps() {
    info "Updating package lists…"
    apt-get update -qq

    info "Installing base dependencies…"
    apt-get install -y -qq \
        curl wget git unzip gnupg2 ca-certificates \
        lsb-release apt-transport-https software-properties-common \
        ufw openssl
    success "Base dependencies installed."
}

install_php() {
    info "Adding PHP 8.3 repository (ondrej/php)…"
    if [[ "$OS_ID" == "ubuntu" ]]; then
        add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
    else
        # Debian
        curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/sury-php.list
    fi
    apt-get update -qq

    info "Installing PHP 8.3 and required extensions…"
    apt-get install -y -qq \
        php8.3 php8.3-fpm php8.3-cli php8.3-common \
        php8.3-sqlite3 php8.3-mbstring php8.3-xml \
        php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath
    success "PHP 8.3 installed."
}

install_composer() {
    if command -v composer &>/dev/null; then
        info "Composer already installed, skipping."
        return
    fi
    info "Installing Composer…"
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        rm /tmp/composer-setup.php
        error "Composer installer checksum mismatch. Installation aborted."
    fi

    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
    success "Composer installed."
}

install_webserver() {
    case "$WEBSERVER" in
        nginx)
            info "Installing Nginx…"
            apt-get install -y -qq nginx
            systemctl enable nginx
            success "Nginx installed."
            ;;
        apache)
            info "Installing Apache2…"
            apt-get install -y -qq apache2 libapache2-mod-fcgid
            a2enmod proxy_fcgi setenvif rewrite headers ssl
            systemctl enable apache2
            success "Apache2 installed."
            ;;
        openlitespeed)
            info "Installing OpenLiteSpeed…"
            wget -qO - https://rpms.litespeedtech.com/debian/enable_lst_debian_repo.sh | bash >/dev/null 2>&1
            apt-get update -qq
            apt-get install -y -qq openlitespeed lsphp83 lsphp83-common lsphp83-sqlite3
            # OLS uses a linked unit file; "enable" may refuse, but the package already configures it
            systemctl enable lsws 2>/dev/null || systemctl enable --now lshttpd 2>/dev/null || true
            systemctl start lsws 2>/dev/null || systemctl start lshttpd 2>/dev/null || true
            success "OpenLiteSpeed installed."
            ;;
    esac
}

install_database() {
    case "$DATABASE" in
        mariadb)
            info "Installing MariaDB…"
            apt-get install -y -qq mariadb-server mariadb-client
            systemctl enable mariadb
            success "MariaDB installed."
            ;;
        mysql)
            info "Installing MySQL…"
            apt-get install -y -qq mysql-server mysql-client
            systemctl enable mysql
            success "MySQL installed."
            ;;
        postgresql)
            info "Installing PostgreSQL…"
            apt-get install -y -qq postgresql postgresql-contrib
            systemctl enable postgresql
            success "PostgreSQL installed."
            ;;
    esac
}

install_mail() {
    if [[ "$INSTALL_MAIL" != true ]]; then
        return
    fi
    info "Installing Postfix and Dovecot…"
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        postfix dovecot-core dovecot-imapd dovecot-pop3d

    systemctl enable postfix dovecot
    success "Postfix and Dovecot installed."
}

# ─────────────────────────────────────────────────────────────
# Application setup
# ─────────────────────────────────────────────────────────────
INSTALL_DIR="/var/www/starhosting"
REPO_URL="https://github.com/sanona-ltd/starhosting.git"

clone_app() {
    if [[ -d "$INSTALL_DIR/.git" ]]; then
        info "StarHosting already cloned, pulling latest…"
        git -C "$INSTALL_DIR" pull --quiet
    else
        info "Cloning StarHosting to $INSTALL_DIR…"
        git clone --quiet "$REPO_URL" "$INSTALL_DIR"
    fi
    success "Application source ready."
}

setup_app() {
    cd "$INSTALL_DIR"

    info "Creating .env file…"
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
    APP_SECRET=$(openssl rand -hex 32)
    sed -i "s/APP_SECRET=changeme/APP_SECRET=${APP_SECRET}/" "$INSTALL_DIR/.env"
    sed -i "s/PANEL_WEBSERVER=nginx/PANEL_WEBSERVER=${WEBSERVER}/" "$INSTALL_DIR/.env"
    sed -i "s/PANEL_DATABASE=mariadb/PANEL_DATABASE=${DATABASE}/" "$INSTALL_DIR/.env"
    if [[ "$INSTALL_MAIL" == true ]]; then
        sed -i "s/PANEL_MAIL=false/PANEL_MAIL=true/" "$INSTALL_DIR/.env"
    fi
    success ".env configured."

    info "Creating var/ directory…"
    mkdir -p "$INSTALL_DIR/var"
    chown -R www-data:www-data "$INSTALL_DIR/var"

    info "Installing PHP dependencies…"
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-dev --optimize-autoloader --quiet
    success "Composer packages installed."

    info "Running database migrations…"
    php "$INSTALL_DIR/bin/console" doctrine:migrations:migrate --no-interaction --quiet
    success "Database schema created."
}

# ─────────────────────────────────────────────────────────────
# SSL certificate
# ─────────────────────────────────────────────────────────────
create_ssl_cert() {
    local ssl_dir="/etc/starhosting/ssl"
    mkdir -p "$ssl_dir"

    info "Generating self-signed SSL certificate for panel…"
    openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
        -keyout "$ssl_dir/panel.key" \
        -out    "$ssl_dir/panel.crt" \
        -subj "/C=US/ST=Panel/L=Panel/O=StarHosting/OU=Panel/CN=starhosting-panel" \
        -quiet 2>/dev/null

    chmod 600 "$ssl_dir/panel.key"
    success "SSL certificate created at $ssl_dir/"
}

# ─────────────────────────────────────────────────────────────
# Virtual host configuration
# ─────────────────────────────────────────────────────────────
configure_panel_vhost() {
    info "Configuring panel virtual host on port 8443…"

    case "$WEBSERVER" in
        nginx)
            cat > /etc/nginx/sites-available/starhosting-panel.conf <<EOF
server {
    listen 8443 ssl;
    server_name _;

    ssl_certificate     /etc/starhosting/ssl/panel.crt;
    ssl_certificate_key /etc/starhosting/ssl/panel.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root ${INSTALL_DIR}/public;
    index index.php;

    access_log /var/log/nginx/starhosting-access.log;
    error_log  /var/log/nginx/starhosting-error.log;

    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\\.php(/|\$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\\.php)(/.*)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        internal;
    }

    location ~ \\.php\$ {
        return 404;
    }
}
EOF
            ln -sf /etc/nginx/sites-available/starhosting-panel.conf \
                   /etc/nginx/sites-enabled/starhosting-panel.conf
            systemctl restart nginx
            ;;

        apache)
            cat > /etc/apache2/sites-available/starhosting-panel.conf <<EOF
<VirtualHost *:8443>
    DocumentRoot ${INSTALL_DIR}/public
    ServerName starhosting-panel

    SSLEngine on
    SSLCertificateFile    /etc/starhosting/ssl/panel.crt
    SSLCertificateKeyFile /etc/starhosting/ssl/panel.key

    <Directory ${INSTALL_DIR}/public>
        AllowOverride None
        Order Allow,Deny
        Allow from All

        FallbackResource /index.php
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/starhosting-error.log
    CustomLog \${APACHE_LOG_DIR}/starhosting-access.log combined
</VirtualHost>
EOF
            # Ensure port 8443 is listened on
            grep -q "Listen 8443" /etc/apache2/ports.conf || echo "Listen 8443" >> /etc/apache2/ports.conf
            a2enmod ssl >/dev/null 2>&1
            a2ensite starhosting-panel >/dev/null 2>&1
            systemctl restart apache2
            ;;

        openlitespeed)
            warn "OpenLiteSpeed panel vhost must be configured manually via the LiteSpeed WebAdmin at https://server-ip:7080"
            ;;
    esac
    success "Panel virtual host configured."
}

# ─────────────────────────────────────────────────────────────
# Firewall
# ─────────────────────────────────────────────────────────────
configure_firewall() {
    info "Configuring UFW firewall…"
    ufw --force enable >/dev/null 2>&1 || true
    ufw allow OpenSSH >/dev/null 2>&1 || true
    ufw allow 80/tcp  >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true
    ufw allow 8443/tcp >/dev/null 2>&1 || true

    if [[ "$INSTALL_MAIL" == true ]]; then
        ufw allow 25/tcp  >/dev/null 2>&1 || true
        ufw allow 587/tcp >/dev/null 2>&1 || true
        ufw allow 993/tcp >/dev/null 2>&1 || true
        ufw allow 995/tcp >/dev/null 2>&1 || true
    fi
    success "Firewall rules applied."
}

# ─────────────────────────────────────────────────────────────
# Final success message
# ─────────────────────────────────────────────────────────────
print_success() {
    local server_ip
    server_ip=$(hostname -I | awk '{print $1}' 2>/dev/null || echo "YOUR_SERVER_IP")

    echo ""
    echo -e "${BOLD}${GREEN}"
    echo "  ╔═══════════════════════════════════════════════════╗"
    echo "  ║       🎉  StarHosting Installed Successfully!      ║"
    echo "  ╚═══════════════════════════════════════════════════╝"
    echo -e "${RESET}"
    echo -e "  Panel URL:   ${BOLD}${CYAN}https://${server_ip}:8443${RESET}"
    echo ""
    echo -e "  ${BOLD}Next steps:${RESET}"
    echo "  1. Create your admin user:"
    echo -e "     ${CYAN}php ${INSTALL_DIR}/bin/console app:setup${RESET}"
    echo ""
    echo "  2. Open your browser and navigate to:"
    echo -e "     ${CYAN}https://${server_ip}:8443/login${RESET}"
    echo ""
    echo "  3. Accept the self-signed SSL certificate warning"
    echo "     (or replace with a Let's Encrypt cert later)."
    echo ""
    echo -e "  ${YELLOW}Note:${RESET} Install dir: ${INSTALL_DIR}"
    echo -e "  ${YELLOW}Note:${RESET} Logs: /var/log/${WEBSERVER}/starhosting-*.log"
    echo ""
}

# ─────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────
main() {
    banner
    check_root
    check_os
    choose_webserver
    choose_database
    choose_mail
    show_summary

    echo ""
    info "Starting installation…"
    echo ""

    install_base_deps
    install_php
    install_composer
    install_webserver
    install_database
    install_mail
    clone_app
    setup_app
    create_ssl_cert
    configure_panel_vhost
    configure_firewall

    print_success
}

main "$@"
