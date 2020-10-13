# Generate dependency graph for Magento modules

## install on MacOS
 - `git clone https://github.com/zamoroka/magento-dependency-graph.git` 
 - `brew install graphviz`
 - install [OmnigGaffle](https://www.omnigroup.com/omnigraffle/) to view and edit .dot files (optional)
 
# usage
 - run `sh getDependencyGraph.sh "path-to-the-magento-2-folder"`. It will generate .dot, .pdf and .svg files

# legend
- orange text - module is in app/code directory
- green text - module is in vendor directory
- blue block - module is independent
- red arrow two modules are dependent on each other
