SHELL:=/bin/bash
VERSION=$(shell grep -o '^[0-9]\+\.[0-9]\+\.[0-9]\+' CHANGELOG.rst | head -n1)
FILENAME=komtetkassa.zip

# Colors
Color_Off=\033[0m
Red=\033[1;31m

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

version:  ## Версия проекта
	@echo -e "${Red}Version:${Color_Off} $(VERSION)";

build:  ## Собрать контейнер
	@sudo chmod -R 777 php/ &&\
	docker-compose build --no-cache

start_web:  ## Запустить контейнер
	@docker-compose up -d web

stop:  ## Остановить контейнер
	@docker-compose down

update:  ## Обновить плагин для фискализации
	@rsync -av --delete src/modules/ php/custom/addon_415_addonskomtetkassa/modules/

release:  ## Архивировать для загрузки в маркет
	@mkdir -p dist
	@rm -f dist/$(FILENAME) || true
	@cd src && zip -r ../dist/$(FILENAME) modules -x '*docker_env*' '*test*' '*examples*'

.PHONY: version  release
.DEFAULT_GOAL := version