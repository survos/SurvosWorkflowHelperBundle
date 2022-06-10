import { Controller } from '@hotwired/stimulus';

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['msg']
    static values = {
        duration: {type: Number, default: 2000},
        title: {type: String, default: 'Hola' }
    }

    connect() {
        let msg = 'Hello from @survos/workflow-bundle: ' + this.identifier;
        this.show();
    }

    show() {
        console.log('use d3 to create the svg...');
    }

    // ...
}
