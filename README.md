# Generate dependency graph for Magento modules

## install
 - get repo
   - `git clone https://github.com/zamoroka/magento-dependency-graph.git`
   - `cd magento-dependency-graph`
   - `composer install`
 - get graphviz:
   - `brew install graphviz` MacOS
   - `sudo apt install graphviz` Ubuntu
 - install [OmnigGaffle](https://www.omnigroup.com/omnigraffle/) to view and edit .dot files (optional)
 
## usage
 - run `sh getDependencyGraph.sh "path-to-the-magento-2-folder" "ModuleVendor"` to generate `.dot`, `.pdf` and `.svg` files.
 - view files in root folder

## example of generated dependency graph
![example](https://github.com/zamoroka/magento-dependency-graph/blob/master/example.svg?raw=true)

#### legend
- ![](https://via.placeholder.com/15/ffa500?text=+) orange **text** - module is in app/code directory
- ![](https://via.placeholder.com/15/00FF00?text=+) green **text** - module is in vendor directory
- ![](https://via.placeholder.com/15/ff0000?text=+) red **text** - module is not exists
- ![](https://via.placeholder.com/15/1589F0?text=+) blue **block** - module is independent
- ![](https://via.placeholder.com/15/ff0000?text=+) red **arrow** - two modules are dependent on each other
