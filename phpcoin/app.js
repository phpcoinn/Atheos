const { createApp, ref } = Vue;

const settings = JSON.parse(localStorage.getItem('phpcoinSettings'));

createApp({

    data() {
        return {
            engine: 'virtual',
            engines: {},
            address: null,
            contract: null,
            addresses: []
        }
    },
    computed: {
        virtual() {
            return this.engine === 'virtual';
        }
    },
    mounted() {
        this.engines = engines;
        console.log("settings",settings);
        this.addresses = settings.addresses;
        if(!this.addresses) {

        }
    },
    methods: {
        save() {
            let saveData = {
                engine: this.engine
            };
            localStorage.setItem('phpcoinSettings', JSON.stringify(saveData));
        },
        reset() {
            localStorage.removeItem('phpcoinSettings');
        },
        refresh() {
            document.location.reload();
        },
        loadSettings() {
            let data = localStorage.getItem('phpcoinSettings');
            if(data) {
                data = JSON.parse(data);
            }
            return data;
        }
    }

}).mount("#app");
