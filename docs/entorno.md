# Entorno de desarrollo

## Resumen

El entorno documentado para **KeyDeploy** se preparó sobre una máquina virtual Ubuntu y contiene las herramientas necesarias para desarrollar y ejecutar la aplicación Laravel inicial.

| Componente | Versión o configuración indicada | Verificación indicada |
|---|---|---|
| Ubuntu | 26.04.1 LTS | `lsb_release -a` o `cat /etc/os-release` |
| Arquitectura | x86_64 | `uname -m` |
| Visual Studio Code | 1.141.0 x64 | `code --version` |
| PHP | 8.5.4 | `php -v` |
| Composer | 2.9.5 | `composer --version` |
| Git | 2.53.0 | `git --version` |
| Node.js | v24.21.0 | `node --version` |
| npm | 12.2.0 | `npm --version` |
| MySQL Server | 8.4.11 | `sudo mysql` |
| Laravel | 13.35.0 | `php artisan --version` |
`npm 12.2.0` es la versión indicada para el entorno.\n\n
## Máquina virtual y sistema operativo

| Dato | Valor |
|---|---|
| Sistema | Ubuntu 26.04.1 |
| Arquitectura | x86_64 |
| Hostname | `proyectogaca` |
| Usuario de desarrollo | `proyectogaca` |
| Tipo | Máquina virtual en VirtualBox |
| CPU / RAM | 4 GB de RAM (la plantilla no indica el número de CPU) |
| IP indicada | `192.168.202.82/24` |
| Origen | Imagen ISO de Ubuntu 26.04.1 |

La actualización inicial se realizó con:

```bash
sudo apt update
sudo apt upgrade
```

`apt update` actualiza el índice local de paquetes disponibles y `apt upgrade` instala las actualizaciones compatibles de los paquetes instalados.

## Preparación del entorno

La secuencia de preparación descrita en la plantilla es:

1. Actualizar Ubuntu con `sudo apt update` y `sudo apt upgrade`.
2. Instalar Visual Studio Code y las herramientas base.
3. Instalar PHP y las extensiones requeridas por Laravel.
4. Instalar Composer para gestionar las dependencias PHP.
5. Instalar Git y configurar la identidad de cada desarrollador.
6. Instalar NVM, Node.js y npm para las herramientas frontend.
7. Instalar y configurar MySQL.
8. Crear el proyecto Laravel y comprobar su arranque.

### Visual Studio Code

Visual Studio Code es el editor principal. Se utiliza para editar y gestionar el código, abrir una terminal integrada y trabajar con Git.

Extensiones mencionadas en la plantilla:

| Extensión | Función | Carácter indicado |
|---|---|---|
| PHP Intelephense (`bmewburn.vscode-intelephense-client`) | Autocompletado, navegación a definiciones, diagnóstico y documentación de PHP | Obligatoria |
| Laravel Blade Snippets (`onecentlin.laravel-blade`) | Resaltado y fragmentos para plantillas Blade | Opcional |
| GitLens (`eamodio.gitlens`) | Información del historial y autoría de cambios | Opcional |
| DotENV (`mikestead.dotenv`) | Ayuda de lectura y edición de archivos `.env` | Opcional |

### PHP y extensiones

PHP es el lenguaje que ejecuta la lógica del servidor. Laravel es un framework construido sobre PHP. Las extensiones amplían las funciones disponibles para tareas como trabajar con texto UTF-8, XML, peticiones HTTP, archivos ZIP y conexiones MySQL.

La instalación indicada utiliza los paquetes de Ubuntu:

```bash
sudo apt install -y php php-cli php-mbstring php-xml php-curl php-zip php-mysql
```

Extensiones documentadas:

| Paquete / módulo | Uso indicado | Comprobación indicada |
|---|---|---|
| `mbstring` | Manejo de cadenas multibyte y UTF-8 | `php -m \| grep -i mbstring` |
| `xml` (`dom`, `xmlwriter`, `simplexml`) | Lectura y generación de XML/HTML; dependencias de Composer y PHPUnit | `php -m \| grep -i -E "dom\|xml"` |
| `curl` | Peticiones HTTP | `php -m \| grep -i curl` |
| `zip` | Gestión de archivos ZIP y paquetes | `php -m \| grep -i zip` |
| `mysql` (`mysqlnd`, `pdo_mysql`) | Conexión PHP/Laravel con MySQL mediante PDO | `php -m \| grep -i -E "mysqlnd\|pdo_mysql"` |
| `ctype`, `fileinfo`, `openssl`, `pdo`, `tokenizer`, `json` | Funciones base utilizadas por Laravel | `php -m \| grep -i -E "ctype\|fileinfo\|openssl\|pdo\|tokenizer"` |

La versión se consulta con `php -v` y los módulos cargados con `php -m`.

### Composer

Composer es el gestor estándar de dependencias PHP. `composer.json` declara las dependencias directas y sus rangos de versión; `composer.lock` fija las versiones resueltas. Composer guarda los paquetes en `vendor/` y genera el autoload que carga clases automáticamente.

Instalación y comprobación indicadas:

```bash
sudo apt install -y composer
composer --version
```

### Git y GitHub

Git registra el historial local de cambios mediante commits y ramas. La plantilla describe el uso de un remoto para compartir cambios, `push` para enviar commits, `pull` para integrar cambios del remoto, y Pull Requests para revisar cambios antes de integrarlos.

```bash
sudo apt install -y git curl unzip
git --version
git config --global user.name "Nombre Apellidos"
git config --global user.email "correo@example.com"
```

La identidad debe configurarse con los datos reales de cada integrante. La plantilla identifica el remoto como [KeyDeploy en GitHub](https://github.com/Gabriel061122/KeyDeploy) y menciona las ramas `main`, `develop`, `test` y `feature/entorno`. No se incluyen aquí resultados de comandos ni se da por verificado el estado actual de esas ramas.

### Node.js, npm y Vite

Node.js ejecuta las herramientas frontend; npm gestiona sus paquetes, declarados en `package.json`. Vite procesa y empaqueta CSS y JavaScript, y ofrece recarga durante el desarrollo.

La plantilla propone instalar NVM y la versión LTS:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
command -v nvm
nvm install --lts
node --version
npm --version
nvm current
```

Tras instalar NVM, se indica cerrar y volver a abrir la terminal.

## MySQL y configuración local

La configuración descrita usa MySQL en lugar de SQLite:

| Dato | Configuración indicada |
|---|---|
| Servidor | MySQL Server 8.4.11 |
| Puerto | 3306; conexión local en `127.0.0.1` |
| Base de datos | `keydeploy`, con codificación `utf8mb4` |
| Usuario de aplicación | `laravel_user`, con permisos sobre la base del proyecto |

Instalación y pasos de configuración propuestos:

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
sudo systemctl status mysql
```

Desde la consola de MySQL se propone crear la base de datos y un usuario local. El marcador de contraseña debe sustituirse localmente; no se debe guardar una contraseña real en la documentación:

```sql
CREATE DATABASE keydeploy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laravel_user'@'localhost' IDENTIFIED BY '<contraseña local>';
GRANT ALL PRIVILEGES ON keydeploy.* TO 'laravel_user'@'localhost';
```

Laravel obtiene la conexión de las variables `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` del archivo local `.env`, mediante la configuración de `config/database.php` y PDO.

El archivo `.env` contiene configuración específica de cada entorno, incluida `APP_KEY` y las credenciales locales de base de datos. No debe publicarse ni incluir secretos en la documentación. El repositorio debe contener únicamente `.env.example` sin valores secretos; `.env` debe estar excluido mediante `.gitignore`.

## Proyecto Laravel

La plantilla indica Laravel 13.35.0 y propone crear el proyecto y añadir el remoto de GitHub así:

```bash
composer create-project laravel/laravel KeyDeploy
cd KeyDeploy
git init
git remote add origin https://github.com/Gabriel061122/KeyDeploy.git
```

Para iniciarlo en desarrollo, se indican el servidor de Laravel y Vite en terminales separadas:

```bash
php artisan serve
npm run dev
```

La URL local indicada es `http://127.0.0.1:8000/`. La configuración de base de datos debe completarse en `.env` antes de ejecutar las migraciones:

```bash
php artisan migrate
```

La plantilla describe el estado esperado como un proyecto Laravel inicial, sin las funcionalidades del proyecto anual. También propone una página de comprobación con el nombre KeyDeploy, un mensaje de entorno preparado y las versiones del framework y PHP; esta descripción procede de la plantilla y no constituye una verificación independiente del código disponible.

## Reproducción en una máquina Ubuntu nueva

Los pasos de reproducción proporcionados por la plantilla son:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip
sudo snap install --classic code
sudo apt install -y php php-cli php-mbstring php-xml php-curl php-zip php-mysql
sudo apt install -y composer
```

Instalar NVM y Node.js LTS, reiniciar la terminal y verificar `node --version` y `npm --version`. Instalar MySQL, completar la configuración inicial y crear `keydeploy` y `laravel_user` según el apartado anterior.

Clonar y configurar el proyecto:

```bash
git clone https://github.com/Gabriel061122/KeyDeploy.git
cd KeyDeploy/proyecto_laravel
composer install
cp .env.example .env
php artisan key:generate
```

Completar las variables `DB_*` del `.env` con los valores locales, sin publicar el archivo. Después:

```bash
php artisan migrate
npm install
npm run build
php artisan serve
```

En otra terminal, ejecutar `npm run dev` si se necesita Vite en modo desarrollo. La plantilla señala que en Ubuntu 26.04 se utilice PHP de los repositorios oficiales, que se configure MySQL en `.env` antes de migrar y que se ejecute `npm audit` tras instalar dependencias.

## Incidencias y decisiones técnicas descritas

- **PPA de PHP incompatible con Ubuntu 26.04:** el PPA de Ondřej no publicaba paquetes para `resolute`. La plantilla describe retirarlo e instalar PHP desde los repositorios oficiales de Ubuntu.
- **Controlador SQLite ausente al migrar:** Laravel nuevo usa SQLite por defecto; se describe cambiar `.env` a MySQL y comprobar que `pdo_mysql` esté cargado antes de migrar.
- **Avisos de npm audit:** se describen vulnerabilidades en dependencias de desarrollo relacionadas con `concurrently` y `shell-quote`, y la aplicación de `npm audit fix --force`. La plantilla indica comprobar después con `npm audit` y `npm run build`.

## Seguridad del repositorio

La configuración de entorno debe mantenerse fuera del repositorio cuando incluya valores reales. La plantilla indica revisar `.gitignore`, conservar `.env.example` como plantilla sin secretos y comprobar los archivos preparados antes de cada commit. No deben publicarse contraseñas, tokens, claves privadas ni valores reales de `.env`.

## Archivos del repositorio relacionados

La estructura descrita para la entrega incluye `docs/` y el proyecto Laravel en `proyecto_laravel/`, con archivos como `.env.example`, `.gitignore`, `README.md`, `composer.json` y `package.json`. Esta documentación se limita al entorno de desarrollo: no incorpora contenido de evidencias, capturas, historial de commits, contribuciones individuales ni conclusiones que dependan de comprobaciones del equipo.

