help:
	# make deps    	      - Install dependencies.
	# make builddocs      - Convert WordPress the readme.txt into GitHub README.md markdown.
	# make installtestenv - Install unit testing environment.
	# make test           - Run unit tests.
	# make clean          - Remove build and test junk from filesystem.
	# make deploy         - Deplol.
	#
	# Requirements for everything to work:
	# * composer - Check out https://getcomposer.org/
	# * make
	# * php - As command line program

all: deps builddocs

deps:
	composer install

builddocs: deps
	vendor/bin/wp2md convert < readme.txt > README.md

installtestenv: deps
	mysql -e "DROP DATABASE IF EXISTS stop_xmlrpc_attack_test;" --user=root --password=""
	bin/install-wp-tests.sh stop_xmlrpc_attack_test root ""

test:
	vendor/bin/phpunit

deploy:
	bin/deploy.sh

clean:
	rm -Rf vendor composer.lock

.PHONY: help all deps builddocs installtestdev clean
