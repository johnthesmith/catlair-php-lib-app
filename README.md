# app

1. This repository contains application php code for Catlair.
0. The project provides the ability to develop console applications based on payloads.



## Dependenсes

1. This project has dependencies on third-party software and projects.
    1. [Software](#software)
    0. [Projects](#projects)

### Software

1. The following software will need to be installed:
    1. php 8.x
    0. php-yaml
    0. php-json
    0. php-mbstring

### Projects

1. The following projects are required for use:
    1. https://github.com/johnthesmith/catlair-php-lib-core



## Class inheritance diagramm

```mermaid
classDiagram

    class App {
        + baisic application
        + configuration cli+config+env
        + logger
        + monitoring
    }

    class Engine {
        + communicates with payload
        + multiprojects
    }

    class Payload {
        + manages payload logic
        + interacts with params
    }

    class Hub {
        + works with files
        + works with states
        + sql scripts
    }

    class Daemon {
        + fork
        + systemctl
    }

    class Web {
        + Wab application
        + Templater
    }

    class Params {
    }

    class Result {
    }

    App --|> Params : extends
    Params --|> Result : extends
    Engine --|> App : extends
    Daemon --|> Engine : extends
    Web --|> Engine : extends
    Payload --|> Params : extends
    Hub --|> Payload : extends
```



## Inclusions Diagram

```mermaid
flowchart LR

subgraph App
    app[app.php]
    engine[engine.php]
    daemon[daemon.php]
    payload[payload.php]
    hub[hub.php]
end

subgraph Core
    log[log.php]
    mon[mon.php]
    params[params.php]
    parse[parse.php]
    store_utils[store_utils.php]
end

hub --> payload
app --> log
app --> mon
app --> params
app --> parse
payload --> engine
engine --> payload

engine --> app
daemon --> engine
hub --> store_utils
```

