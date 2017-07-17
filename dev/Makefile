RCMAIL_VERSION=1.2.5
RCMAIL_DOWNLOAD=https://github.com/roundcube/roundcubemail/releases/download/$(RCMAIL_VERSION)/roundcubemail-$(RCMAIL_VERSION)-complete.tar.gz

SABREDAV_VERSION=3.2.2
SABREDAV_DOWNLOAD=https://github.com/fruux/sabre-dav/releases/download/$(SABREDAV_VERSION)/sabredav-$(SABREDAV_VERSION).zip

server: logs temp htdocs/roundcube htdocs/sabrecube htdocs/SabreDAV \
	database.sqlite
	php -c php.ini -S 0.0.0.0:8080 -t htdocs \
		-d auto_prepend_file=$(CURDIR)/prepend.php

logs temp htdocs:
	mkdir -p $@

htdocs/roundcube: | htdocs
	mkdir $@
	wget -q -O- $(RCMAIL_DOWNLOAD) | tar xz -C $@ --strip-components=1
	ln -s ../../../config.inc.php htdocs/roundcube/config/config.inc.php

htdocs/sabrecube: | htdocs
	ln -s ../../sabrecube $@

htdocs/SabreDAV: | htdocs
	wget -q -Otmp.zip $(SABREDAV_DOWNLOAD)
	unzip -q tmp.zip
	rm tmp.zip
	mv SabreDAV htdocs

database.sqlite: | htdocs/roundcube
	sqlite3 $@ < ../sql/sabrecube.sql
	sqlite3 $@ < htdocs/roundcube/SQL/sqlite.initial.sql
