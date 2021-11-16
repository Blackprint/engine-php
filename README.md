<p align="center"><a href="#" target="_blank" rel="noopener noreferrer"><img width="150" src="https://user-images.githubusercontent.com/11073373/141421213-5decd773-a870-4324-8324-e175e83b0f55.png" alt="Blackprint"></a></p>

<h1 align="center">Blackprint Engine for PHP</h1>
<p align="center">Run exported Blackprint on PHP environment.</p>

<p align="center">
    <a href='https://github.com/Blackprint/Blackprint/blob/master/LICENSE'><img src='https://img.shields.io/badge/License-MIT-brightgreen.svg' height='20'></a>
</p>

## Documentation
> Warning: This project haven't reach it stable version (semantic versioning at v1.0.0)<br>
> But please try to use it and help improve this project

Node and Interface module is class design oriented only.

---
### Defining Blackprint Node and Interface
Because PHP does support Object Oriented programming and to make the node import more effective and easier, this engine will only support Node/Interface that declared with classes.

But before that, we need to create a folder to store our Node/Interface logic. For the example `/BPNode`.

#### Registering Namespace folder

```php
require_once('../vendor/autoload.php');

// This can be called on different PHP libraries
\Blackprint\registerNamespace(__DIR__.'/BPNode');

// When registering with Namespace, the default root namespace is always "BPNode"
// Please name your nodes namespace along with your library name to avoid conflict with other library
```

#### Define Node and Interface
After the namespace folder `./BPNode` has been registered, you can now create a folder with your library name. For the example: `./BPNode/Example`.

```php
// file: ./BPNode/Example/Hello.php
namespace \BPNode\Example;

// The class name must match with the file name
// This will be registered as Node definition
class Hello extends \Blackprint\Node {
    function __construct($instance){
        // Call the parent constructor first, passing the $instance (Blackprint\Engine)
        parent::__construct($instance);

        // Set the Interface, let it empty if you want
        // to use default empty interface "setInterface()"
        $iface = $this->setInterface('BPIC/Example/Hello');
        $iface->title = "Hello"; // Set the title for debugging

        // Please remember to capitalize the port name
        // Set the output port structure for your node (Optional)
        $this->output = [
            'Changed'=> Types::Function,
            // Callable: $this->output['Changed']()

            'Output'=> Types::Number,
            // $this->output['Value'] = 246
        ];

        // Set the input port structure for your node (Optional)
        $this->input = [
            'Multiply'=> Types::Number,
            // $val = $this->output['Value']
        ]
    }
}
```

Because Node is supposed to contain structure only it should be designed to be simple, the another complexity like calling system API or providing API for developer to interact with your node should be placed on Interface class.

```php
// same file: ./BPNode/Example/Hello.php
namespace \BPNode\Example;

// Your Interface namespace must use "BPIC" as the prefix
\Blackprint\registerInterface('BPIC\Example\Hello', HelloIFace::class);
class HelloIFace extends \Blackprint\Interfaces {
    function __construct($node){
        // Call the parent constructor first, passing the $node (Blackprint\Node)
        parent::__construct($node);
        // $this->node => Blackprint\Node

        // Define IFace's data (optional if you want to export/import data from JSON)
        // Because getter/setter feature only available on class, we will create from `class MyData`
        $this->data = new MyData($this);
        // $this->data->value === 123 (if the default value is not replaced when importing JSON)
    }

    function recalculate(){
        // Get value from input port
        $multiplyBy = $this->node->input['Multiply']();

        // Assign new value to output port
        $this->node->output['Output']($this->data->value * $multiplyBy);
    }
}

// Getter and setter should be changed with basic property accessor
class MyData {
    // Constructor promotion, $iface as private MyData property
    function __construct(private $iface){}

    // Draft: please design it like below after
    // this PR was merged to PHP https://github.com/php/php-src/pull/6873
    private $_value = 123;
    public $value {
        get { return $this->_value };
        set {
            $this->_value = $value;
            $this->iface->recalculate(); // Call recalculate() on HelloIFace
        };
    };

    // Current polyfill for property accessor (https://github.com/php/php-src/pull/6873)
    private $data = ["value"=> 123];
    function __get($key) {
        return $this->data[$key];
    }

    function __set($key, $val) {
        $this->data[$key] = &$val;

        if($key === 'value')
            $this->iface->recalculate($val); // Call recalculate() on HelloIFace
    }
}
```

## Creating new Engine instance

```php
// Create Blackprint Engine instance
$instance = new Blackprint\Engine();

// You can import nodes with JSON
// if the nodes haven't been registered, this will throw an error
$instance->importJSON(`{...}`);

// You can also create the node dynamically
$iface = $instance->createNode('Example\Hello', /* [..options..] */);

// ----

// Change the default data 'value' property
$iface->data->value = 123;

// Assign the 'Multiply' input port = 2
$iface->node->input['Multiply'](2);

// Get the value from 'Output' output port
echo $iface->node->output['Output'](); // 246
```


---

### Example
![VEfiZCFQAi](https://user-images.githubusercontent.com/11073373/141419539-dbee7bae-946c-4eb4-969b-118b77e07d18.png)

This repository provide an example with the JSON too, and you can try it with PHP CLI:<br>

```sh
# Change your working directory into empty folder first
$ git clone --depth 1 https://github.com/Blackprint/engine-php .
$ composer install
$ php ./example/init.php
```