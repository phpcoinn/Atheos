const { createApp, ref, computed } = Vue;

const MAIN_DAPPS_ID = 'PeC85pqFgRxmevonG6diUwT4AfF7YUPSm3';

const app = createApp({

    data() {
        return {
            engines: [],
            engine: null,
            accounts: {},
            wallet: null,
            walletAddress: null,
            walletBalance: null,
            contractWallet: null,
            contract: {},
            contractSources: [],
            interfaceTab: 'methods',
            methodType: 'exec',
            methodAmount: 0,
            methodParams: {},
            viewParams: {},
            methodAddressValue: null,
            contractAccount: {},
            contractSource: null,
            outTab: 1,
            transactions: [],
            state: {},
            debug_logs: [],
            sendAddress: null,
            viewResponses: {},
            propertyKeys: {},
            propertyValues: {},
            deployParams: {}
        }
    },
    computed: {
        virtual() {
            return this.engine && this.engine.name === 'virtual';
        },
        node() {
            return this.engine && this.engine.node;
        },
        methodAddress: {
            get() {
                console.log("methodAddress get")
                return this.contractWallet && this.methodType === 'exec' ? this.contractWallet.address : this.methodAddressValue
            },
            set(nv) {
                console.log("methodAddress set", nv)
                this.methodAddressValue = this.contractWallet && this.methodType === 'exec' ? this.contractWallet.address : nv
            }
        },
    },
    mounted() {
        this.load()
    },
    methods: {
        api(q, data, cb) {
            fetch('/phpcoin/api.php?q=' + q, {method:'POST', body: JSON.stringify(data)}).then(res => res.json().then(r => {
                if(r.status === 'error') {
                    alert(r.data)
                    return;
                }
                if(cb) cb(r.data)
            })).catch(err=>{
                alert(err.message)
            })
        },
        reset() {
            this.api('reset',{}, this.load)
        },
        load() {
            this.api('load', {}, res=> {
                this.engines=res.engines
                this.engine = res.engine
                this.accounts = res.accounts
                this.wallet = res.wallet
                this.contractWallet = res.contractWallet
                this.contractSources = res.contractSources
                this.contract = res.contract
                this.transactions = res.transactions
                this.node = res.node
                this.state = res.state
                this.debug_logs = res.debug_logs
                this.deployParams = JSON.stringify(res.deployParams) === '[]' ? {} : res.deployParams
            })
        },
        changeEngine() {
            this.api('changeEngine', {name: this.engine.name}, this.load)
        },
        changeAccount() {
            this.api('changeAccount', {address: this.wallet.address}, this.load)
        },
        generateAccount() {
            this.api('generateAccount', {}, this.load)
        },
        generateScWallet() {
            this.api('generateScWallet', {}, this.load)
        },
        compile() {
            let data = {
                address: this.contractWallet.address,
                source: this.contract.source
            }
            this.api('compile', data, this.load)
        },
        getSource() {
            window.open('/phpcoin/api.php?q=getSource', '_blank')
        },
        deploy() {
            let data = {
                contract: this.contract,
                deployParams: this.deployParams,
            }
            this.api('signDeploy', data, (contract)=>{
                //sign tx
                if(this.virtual) {
                    let signature = window.sign(this.engine.chainId + contract.txdata, this.wallet.private_key)
                    console.log(signature)
                    let data = {
                        signature:signature
                    }
                    this.api('deploy', data, ()=>{
                        this.load()
                    })
                } else {
                    this._signMessage(this.engine.atheos_url + '/phpcoin/api.php?q=deployReal', this.wallet.address, contract.txdata)
                }
            })
        },
        _signMessage(redirectUrl, address, data){
            let redirect = encodeURI(redirectUrl)
            let url = `${this.node}/dapps.php?url=${MAIN_DAPPS_ID}/gateway/sign.php?&app=Atheos&address=${address}&redirect=${redirect}&no_return_message=1`
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'message';
            input.value = data
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },
        callMethod(name) {
            let params = this.methodParams
            console.log({params})
            let data = {
                address: this.methodType === 'exec' ? this.wallet.address : this.contractWallet.address,
                fn_address: this.methodType === 'exec' ?  this.methodAddress : this.sendAddress,
                amount: this.methodAmount,
                method_type: this.methodType,
                exec_method: name,
                exec_params: this.methodParams[name]
            }
            this.api('callMethod', data, res=>{
                if(this.virtual) {
                    this.load()
                } else {
                    document.location.href = res
                }
            })
        },
        callView(name) {
            let data = {
                method: name,
                view_params:this.viewParams[name]
            }
            this.api('callView', data, res => {
                this.viewResponses[name] = res
            })
        },
        getProperty(name) {
            let data = {
                name,
                key: this.propertyKeys[name]
            }
            this.api('getProperty', data, res => {
                this.propertyValues[name] = res
            })
        },
        initMethodParamMap(name) {
            if(!this.methodParams[name]){
                this.methodParams[name]={}
            }
        },
        initViewParamMap(name) {
            if(!this.viewParams[name]){
                this.viewParams[name]={}
            }
        },
        txType(tx) {
            let type
            type = tx.type
            if(type === 5) return 'Deploy'
            if(type === 6) return 'Exec'
            if(type === 7) return 'Send'
        },
        txMethod(tx) {
            let type = tx.type
            if(type === 5) return 'Deploy'
            if(type === 6 || type === 7) {
                let data = JSON.parse(atob(tx.message))
                return data.method
            }
        },
        txParams(tx) {
            let type = tx.type
            if(type === 5) {
                return JSON.parse(atob(tx.data)).params
            }
            if(type === 6 || type === 7) {
                return JSON.parse(atob(tx.message)).params
            }
        },
        generateSendAddress() {
            let account = window.generateAccount()
            this.sendAddress = account.address
        },
        refresh() {
            this.load()
        },
        loginWallet() {
            this.api('loginWallet', {}, (res)=>{
                document.location.href=res
            })
        },
        logoutWallet() {
            this.api('logoutWallet', {}, this.load)
        },
        loginScWallet() {
            this.api('loginScWallet', {}, (res)=>{
                document.location.href=res
            })
        },
        logoutScWallet() {
            this.api('logoutScWallet', {}, this.load)
        }
    }

});
app.mount("#app");
