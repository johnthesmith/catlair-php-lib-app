# app

1. Libraries of Catlair applications.



## Dependenсes

### Software

1. php 8.x
0. php-yaml
0. php-json
0. php-mbsteing

### Projects

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

