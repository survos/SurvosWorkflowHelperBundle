
import { Controller } from '@hotwired/stimulus';

// import { graphviz }  from 'd3-graphviz';
// https://stackoverflow.com/questions/48471651/es6-module-import-of-d3-4-x-fails
// import * as d3 from 'https://unpkg.com/d3?module';

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */

export default class extends Controller {
    static targets = ['graph']
    static values = {
        digraph: {type: String, default: '    digraph G { a -> b [label="  a to b" labeltooltip="this is a tooltip"]; b -> c [label="  another label" ];}' }
    }

    connect() {
        // never got this working :-(
        let msg = 'Hello from @survos/workflow-bundle: ' + this.identifier;
        // this.show();
        // graphviz(this.graphTarget).renderDot(`digraph{a->b}`);
    }

    show() {
        console.log('use d3 to create the svg ');
        const d = this.digraphValue;
        console.log(d);

        graphviz(this.graphTarget)
            .fade(false)
            .renderDot('digraph {a -> b}');
        // d3.select(this.graphTarget).graphviz()
        //     .fade(false)
        //     .renderDot(d);
    }

    // ...
}
