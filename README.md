# Photonisos - deployment kit (Docker Compose)

WordPress + MySQL for the Photonisos digital photo archive of Samos, for the shared VM `ai4talos.aegean.gr`.
Two containers, **one published TCP port**, everything inside two project folders.

| What | Where on the VM |
|---|---|
| This kit (compose file, config, scripts) | `/home/user/photonisos` |
| WordPress files and MySQL data | `/home/user/photonisos/wordpress`, `/home/user/photonisos/db_data` |
| Photo uploads, UpdraftPlus backups, transfer files | `/mnt/ai4talos/photonisos/{uploads,backups,transfer}` |

Nothing is installed on the host except Docker. This kit was prepared without access to the VM, so it has not been run there yet.

---

## 0. Before you start

1. UAegean VPN active, then `ssh user@ai4talos.aegean.gr`.
2. Docker Engine with the Compose plugin: `docker compose version`.
   If it is missing, ask the VM admin to install it (https://docs.docker.com/engine/install/) and add `user` to the `docker` group.
3. A free port (default 8088): `ss -ltnH | grep -q ':8088 ' && echo "8088 is TAKEN" || echo "8088 is free"`

## 1. Put this kit on the VM

**Option A - copy it from your computer:**

    scp -r photonisos-deploy user@ai4talos.aegean.gr:~/photonisos

**Option B - from a private GitHub repo** (read-only deploy key, so no password is stored on the VM):

    ssh-keygen -t ed25519 -f ~/.ssh/photonisos_deploy -N ""
    cat ~/.ssh/photonisos_deploy.pub        # GitHub: repo -> Settings -> Deploy keys -> Add (read-only)
    GIT_SSH_COMMAND="ssh -i ~/.ssh/photonisos_deploy -o IdentitiesOnly=yes" \
      git clone git@github.com:<github-user>/photonisos.git ~/photonisos

## 2. Export the site from Local (on your own computer)

In Local: right-click the site -> **Open site shell**, then:

    wp db export photonisos.sql

The file appears in the site's `app/public` folder. Open a normal terminal in that same folder and run:

    tar -czf wp-content.tar.gz --exclude=wp-content/uploads --exclude=wp-content/updraft --exclude=wp-content/cache --exclude=wp-content/upgrade --exclude=wp-content/wflogs --exclude=wp-content/advanced-cache.php --exclude=wp-content/object-cache.php wp-content
    tar -czf uploads.tar.gz -C wp-content uploads

Also open Local's `wp-config.php`: if it has custom `define(...)` lines besides the database settings, salts and `$table_prefix`, add them to `WORDPRESS_CONFIG_EXTRA` in `docker-compose.yml` (`DISALLOW_FILE_EDIT` is already there). The new `wp-config.php` is generated automatically.

## 3. Copy the export to the VM

    ssh user@ai4talos.aegean.gr 'mkdir -p /mnt/ai4talos/photonisos/{uploads,backups,transfer}'
    scp photonisos.sql wp-content.tar.gz uploads.tar.gz user@ai4talos.aegean.gr:/mnt/ai4talos/photonisos/transfer/

## 4. Unpack, configure, start (on the VM)

    cd ~/photonisos
    cp .env.example .env && chmod 600 .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 16)|; s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$(openssl rand -hex 16)|" .env
    nano .env          # set WP_IMAGE to your WordPress version (same or newer than Local), check WEB_PORT

    mkdir -p wordpress
    tar -xzf /mnt/ai4talos/photonisos/transfer/wp-content.tar.gz -C wordpress
    tar -xzf /mnt/ai4talos/photonisos/transfer/uploads.tar.gz -C /mnt/ai4talos/photonisos

    docker compose up -d
    docker compose logs -f wordpress      # wait for "resuming normal operations", then Ctrl+C
    docker compose exec wordpress chown -R www-data:www-data /var/www/html

If the `chown` fails on `/mnt/ai4talos` (a network share can refuse it), ask the VM admin to make `/mnt/ai4talos/photonisos` writable by uid 33.

## 5. Import the database and set the new address

    bash scripts/import-site.sh /mnt/ai4talos/photonisos/transfer/photonisos.sql \
        photonisos.local http://ai4talos.aegean.gr:8088

`photonisos.local` is the old Local address (check with `wp option get siteurl` in Local). The script imports the dump, replaces both `http://` and `https://` versions of the old address everywhere (serialized data included), flushes caches and prints a sanity check (site URL, number of published photos, users and roles).

## 6. Check that it works

Open `http://ai4talos.aegean.gr:8088/` in a **private window**.

1. Home page and search work without logging in.
2. Login page is at `/portal`; `/wp-login.php` and `/wp-admin` return 404 when logged out. Log in, 2FA works.
3. Search by location, by theme, by year range and a combination returns the right photos.
4. A single photo page shows the metadata table, including the location.
5. Tools -> Photonisos DC XML export works.
6. Log in as the *Τεκμηριωτής* user: restricted menu; add a photo whose Featured Image is larger than 2 MB (this confirms the PHP upload limits).
7. Wordfence -> Firewall -> **Optimize the Wordfence Firewall** (the old setting points to a path on your PC) and follow the prompts.
8. UpdraftPlus -> Backup now; a new file must appear in `/mnt/ai4talos/photonisos/backups`.
9. LiteSpeed Cache -> Toolbox -> Purge All. (On Apache it runs in limited mode: the server is not LiteSpeed.)
10. PHP must not run from uploads:

        docker compose exec wordpress sh -c 'echo "<?php echo \"PHP RUNS\";" > wp-content/uploads/zz-test.php'
        curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8088/wp-content/uploads/zz-test.php    # expect 403
        docker compose exec wordpress rm wp-content/uploads/zz-test.php

    (Locally, under nginx, the old `.htaccess` rule was ignored. Here `config/uploads-no-php.conf` enforces it.)

## 7. Ports to report to the VM admin

| Port | Use |
|---|---|
| `8088/tcp` (value of `WEB_PORT` in `.env`) | WordPress / Apache (the website) |

The database is **not** published. If the site will be served on 80/443 or behind an existing reverse proxy, set `WEB_BIND=127.0.0.1` in `.env` and point the proxy at the port above.

**HTTPS:** before announcing the site publicly, put it behind HTTPS (institutional proxy or a certificate on the VM). Otherwise the admin login and 2FA codes travel unencrypted. When the final address is known, run the replacement again:

    docker compose run --rm wpcli wp search-replace 'http://ai4talos.aegean.gr:8088' 'https://<final-host>' --all-tables --skip-columns=guid

---

## Day-to-day

    docker compose ps                     # status
    docker compose logs -f wordpress      # logs
    docker compose restart                # restart
    docker compose down                   # stop (data stays in ./db_data and /mnt/ai4talos/photonisos)

    # manual database backup
    docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction "$MYSQL_DATABASE"' \
        > /mnt/ai4talos/photonisos/backups/db-$(date +%F).sql

WordPress core, plugin and theme updates are done from wp-admin as usual.

**Real cron (recommended).** Scheduled UpdraftPlus backups rely on WP-Cron, which only runs when someone visits the site. Set `DISABLE_WP_CRON=true` in `.env`, run `docker compose up -d`, then `crontab -e`:

    */5 * * * * cd /home/user/photonisos && docker compose run --rm -T wpcli wp cron event run --due-now >/dev/null 2>&1

**Off-VM backups.** Backups on the VM disappear with the VM. In UpdraftPlus connect a remote destination (Google Drive, SFTP, ...).

## Custom code for the thesis appendix

The site's own code lives in the database (Code Snippets, ACF field groups, CPT UI settings, Customizer CSS), so it is not in this repo. To publish it as readable files, export it into a `custom-code/` folder: Code Snippets -> Export, ACF -> Tools -> Export Field Groups, CPT UI -> Tools -> Get Code, Customizer -> Additional CSS (copy to `custom.css`), plus the child theme folder and the `photonisos-display` plugin from `wp-content`.

## Security notes

- Never commit `.env`, database dumps, `wp-config.php` or the uploads folder: dumps hold password hashes, two-factor secrets and API keys, and the photographs are personal material. `.gitignore` already excludes them; run `git status` before every push.
- The VM password was shared in plain text: change it with `passwd` after the first login and use an SSH key.
- Keep the GitHub repo **private** and add the VM admin as a collaborator (repo -> Settings -> Collaborators).

## Publishing this kit on GitHub

    cd photonisos-deploy
    git init -b main
    git add .
    git status                 # must NOT list .env or any .sql file
    git commit -m "Photonisos deployment kit"
    # create an empty PRIVATE repo named "photonisos" on github.com, then:
    git remote add origin git@github.com:<github-user>/photonisos.git
    git push -u origin main
