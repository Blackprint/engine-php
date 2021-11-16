# 0.3.0

### New Feature
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
- `Blackprint.Interpreter` is changed into `Blackprint.Engine`
- `iface.options` now changed to `iface.data`, you will need to replace `options` from the exported JSON into `data`
- `iface.id` now changed to `iface.i`, you will need to replace `id` from the exported JSON into `i`
- `iface.id` now being used for named ID, and `iface.i` still being the generated index for the nodes

# 0.1.1

### Notes
- Still in development, any contribution welcome
- Please always specify the fixed version when using for your project
- Usually v0.\*.0 will have breaking changes, while v0.0.\* can have new feature or bug fixes