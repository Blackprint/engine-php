# 0.8.15

### Bug Fix
- Fix incorrect condition check
- Catch error to reset flag/status

# 0.8.14

### Bug Fix
- Fix unrecognized type when determining port type

# 0.8.13

### Bug Fix
- Fix error for event and function node
- Fix imported instance event

# 0.8.12

### Features
- Add `initPorts` for dynamically initializing ports
- Emit `destroy` event when the instance was destroyed
- Add experimental feature to lock the instance
- Improve security for environment variable node by using connection rule
- Add experimental `Blackprint.Types.Slot` for ports with lazy type assignment
- Add event nodes feature
- Handle namespaced variable or nodes

### Bug Fix
- Emit internal event when function port was renamed
- Fix event to be emitted to root instance
- Fix function node that was not being initialized if created manually at runtime
- Fix route port connection and array input data
- Immediate init interface for single node creation
- Improve performance and fix execution order for with `StructOf` feature
- Reset updated cable status when disconnected
- Save port configuration and use it for creating function port
- Improve code for step mode execution
- Replace dot settings's internal save name with underscore
- Disable port manipulation on locked instance
- Put id as title if doesn't have custom title
- Avoid calling update on cable connection when the node having input route
- Remove internal marker to avoid dynamic port connection on outer function port
- Fix node update using default input value when cable was disconnected
- Fix dynamic port marker on internal interface
- Fix type assigned on variable node
- Force output port that use union to be Any type
- Move port type re-assigment for output port
- Improve output port's type when using port feature
- Validate namespace name
- Add options to disable cleaning the instance when importing JSON

# 0.8.0

### Features
- Add `waitOnce` for waiting for an event with Promise
- Add pausible execution feature and change node execution order (experimental, please expect changes in version 0.8)

### Bug Fix
- Fix `value` event trigger
- Fix execution order for custom function node
- Fix incorrect value when using function port's node
- Fix cable input order
- Fix route and partial update
- Fix incorrect iface id references
- Fix port switches initialization
- Reset every struct port into null
- Clear port cache when reusing port
- Avoid re-emitting event on updated cable
- Avoid using ArrayOf port for custom function node

### Breaking Changes
- Remove deprecated function `instance->getNode()`, please use `instance->iface[id]` or `instance->ifaceList[index]`

```php
$instance = new Blackprint.Engine();
$instance->importJSON('...');

// Get iface by node id
$instance->getNode('nodeId'); // Before
$instance->iface['nodeId']; // After

// Get iface by node index
$instance->getNode(0); // Before
$instance->ifaceList[0]; // After
```

- `.update` function will no longer receive cable parameter if `.partialUpdate` is not set to true

```php
// Before
class MyNode extends \Blackprint\Node {
    function update($cable){...}
}

// After
class MyNode extends \Blackprint\Node {
    function __construct($instance){
        // ...
        $this->partialUpdate = true;
    }

    function update($cable){...}
}
```

# 0.7.3

### Features
- Add `isGhost` on interface for standalone port

### Bug Fix
- Fix index error when creating new function
- Fix skipped cache removal for route mode

# 0.7.1

### Features
- Add feature to allow output resyncronization

# 0.7.0
Most features is adapted from [engine-js](https://github.com/Blackprint/engine-js).

### Feature
- Finishing environment variables feature
- Add function, variable, environment node support
- Add route feature to handle data flow
- Add `instance->ref` where the `instance` can be `\Blackprint\Engine` this `ref` will have reference to `Output` or `Input` port
- Add `instance->deleteNode` to delete node manually by using code
- `Blackprint->Engine` is now using `CustomEvent`
- and more

### Breaking Changes
- `iface->const` and `node->const` now changed into `iface->ref` and `node->ref`
-Something may get changes

# 0.3.0

### Feature
- You can register nodes with namespace that follow PSR-4 with `Blackprint\registerNamespace`, please see `./example/init.php` for reference

### Breaking Changes
This changes is supposed to improve efficiency, and reduce possible future breaking changes.

- `.outputs, .inputs, .properties` field is changed into `.output, .input, .property` for `node` and `iface`
- `outputs:[]` field is now changed to `output:[]` for JSON export
- `$instance->registerNode()` and `$instance->registerInterface()` will now being replaced with `Blackprint\registerNode()` and `Blackprint\registerInterface()`
- Because PHP support namespace `Blackprint\registerNamespace` is more recommended
- `$instance->registerNode()` and `$instance->registerInterface()` will now accept class instead of function
- Node must extends `Blackprint\Node` and Interface must extends `Blackprint\Interfaces`
- `BPAO` must be changed to `BPIC`
- When constructing Node, `$node->interface = '...'` now must be changed to `$node->setInterface('...')` before accessing the target interface.
- Port `Listener` now changed to event listener, please use `$port->on('value', callback)` instead
- Interface Data now must be a class (to support getter and setter)

# 0.2.0

### Breaking Changes
- `Blackprint->Interpreter` is changed into `Blackprint->Engine`
- `iface->options` now changed to `iface->data`, you will need to replace `options` from the exported JSON into `data`
- `iface->id` now changed to `iface->i`, you will need to replace `id` from the exported JSON into `i`
- `iface->id` now being used for named ID, and `iface->i` still being the generated index for the nodes

# 0.1.1

### Notes
- Still in development, any contribution welcome
- Please always specify the fixed version when using for your project
- Usually v0.\*.0 will have breaking changes, while v0.0.\* can have new feature or bug fixes