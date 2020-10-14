# Generate dependency graph for Magento modules

## install
 - clone repo
   - `git clone https://github.com/zamoroka/magento-dependency-graph.git`
 - get graphviz:
   - `brew install graphviz` MacOS
   - `sudo apt install graphviz` Ubuntu
 - install [OmnigGaffle](https://www.omnigroup.com/omnigraffle/) to view and edit .dot files (optional)
 
## usage
 - run `sh getDependencyGraph.sh "path-to-the-magento-2-folder" "ModuleVendor"`. It will generate .dot, .pdf and .svg files.

## example of generated dependency graph
![example](https://github.com/zamoroka/magento-dependency-graph/blob/master/example.png?raw=true)

#### legend
- orange text - module is in app/code directory
- green text - module is in vendor directory
- blue block - module is independent
- red arrow two modules are dependent on each other
