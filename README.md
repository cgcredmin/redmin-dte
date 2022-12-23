# RedminDTE

Pasarela de conexión con el servicio de impuestos internos

#### Running Docker image in dev mode

    bash start --dev

#### Documentation

    bash start <COMMAND>  <OPTIONS>

    bash start [--dev | --prod | --aws | -run=<container> | --obfuscate | --deobfuscate] [--bash | --adb | -port]

## EXAMPLES

> bash start --dev -p=8080 --bash

Builds a development docker image and runs it in port 8080 once it's built and running it opens a bash shell.

> bash start --prod -p=8080 --bash

Builds a production docker image and runs it in port 8080 once it's built and running it opens a bash shell.

> bash start --aws

Builds a production ready docker image and push it to AWS ECR. You must have AWS ECR configured.

> bash start -run=<container:tag>

Runs a docker container with the specified tag.

:exclamation: By default PORT is 80 and URL is http://localhost for development
and production environments.

---

## OPTIONS

---

-u=<url> : URL to open on device, emulator or browser. Must use with ngrok.

-p=<port> : Port to run the server

--adb : Launch ADB on connected device or emulator with the given url

-b | --bash : Launch bash on container

-h | --help : Show this help

---
