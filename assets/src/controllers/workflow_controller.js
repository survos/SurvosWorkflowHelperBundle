import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2'

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
        let msg = 'Hello from @tacman/ux-tree/hello_controller: ' + this.identifier;
        this.show();
    }

    show() {
        let timerInterval

        Swal.fire({
            title: 'Auto close alert!',
            html:
                this.titleValue,
            timer: this.durationValue,
            didOpen: () => {
                const content = Swal.getHtmlContainer()
                const $ = content.querySelector.bind(content)

                Swal.showLoading()

                timerInterval = setInterval(() => {
                    Swal.getHtmlContainer().querySelector('strong')
                        .textContent = (Swal.getTimerLeft() / 1000)
                        .toFixed(0)
                }, 100)
            },
            willClose: () => {
                clearInterval(timerInterval)
            }
        })

    }

    // ...
}
