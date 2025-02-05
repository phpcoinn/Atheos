<?php
$settings = @$_SESSION['settings'];
?>
<div id="app">

    <div class="flex flex-wrap align-items-center align-content-between p-2 border-bottom-1 m-0 mb-2">
        <div class="">
            <h4>Smart Contract actions</h4>
        </div>
        <div class="ml-auto">
            <button name="reset" @click="reset" class="p-1">Reset</button>
            <button name="refresh" @click="refresh" class="p-1">Refresh</button>
        </div>
    </div>

    <div class="row grid align-items-center m-0">
        <div class="col-12 sm:col-3">Engine:</div>
        <div class="col-12 sm:col-9">
            <select class="m-0 p-1" v-model="engine" @change="changeEngine">
                <template v-for="e in engines" :key="k">
                    <option :value="e">{{e.title}}</option>
                </template>
            </select>
        </div>
    </div>

    <template v-if="virtual">
        <div class="row grid align-items-center border-bottom-1 m-0">
            <div class="col-12 sm:col-3">
                Wallet address:
            </div>
            <div class="col-6 sm:col-5">
                <select v-model="wallet" @change="changeAccount" class="m-0 p-1">
                    <template v-for="account in Object.values(accounts)">
                        <option :value="account">
                            {{account.address}}
                        </option>
                    </template>
                </select>
                <br/>
                {{wallet && wallet.address}}
            </div>
            <div class="col-6 sm:col-4 flex align-items-center">
                <div class="flex-grow-1 px-2" v-if="wallet">
                    Balance:
                    {{wallet.balance}}
                </div>
                <button type="button" @click="generateAccount">Generate</button>
            </div>
        </div>
    </template>
    <template v-else>
        <div class="row grid align-items-center border-bottom-1 m-0">
            <div class="col-12 sm:col-3">
                Wallet address:
            </div>
            <div class="col-6 sm:col-9">
                <div v-if="!wallet" class="">
                    <button type="button" @click="loginWallet" class="p-1" v-if="!wallet">Login</button>
                </div>
                <div v-else class="grid align-items-center px-2">
                    <div>
                        <a :href="`${node}/apps/explorer/address.php?address=${wallet.address}`" target="_blank">
                            {{wallet.address}}
                        </a>
                    </div>
                    <div class="flex-1 text-center">Balance: {{wallet.balance}}</div>
                    <button type="button" @click="logoutWallet" class="p-1" v-if="wallet">Logout</button>
                </div>
            </div>
        </div>
    </template>


    <h4 class="p-2">Smart Contract: {{contract.status}}</h4>
        <div class="grid align-items-center m-0">
            <div class="col-12 sm:col-3">
                Contract address:
            </div>
            <template v-if="virtual">
                <div class="col-6 sm:col-5 flex align-items-center">
                    <input type="text" v-if="contractWallet" v-model="contractWallet.address" class="m-0"/>
                </div>
                <div class="col-6 sm:col-4 flex align-items-center">
                    <div class="flex-grow-1 px-2" v-if="contractWallet">
                        Balance:
                        {{accounts[this.contractWallet.address].balance}}
                    </div>
                    <button type="button" @click="generateScWallet" class="p-1">Generate</button>
                </div>
            </template>
            <template v-else>
                <div class="col-6 sm:col-9 flex align-items-center">
                    <template v-if="contractWallet">
                        <div>
                            <a :href="`${node}/apps/explorer/address.php?address=${contractWallet.address}`">
                                {{contractWallet.address}}
                            </a>
                        </div>
                        <div class="flex-1 text-center">
                            Balance: {{contractWallet.balance}}
                        </div>
                        <button type="button" @click="logoutScWallet" class="p-1">Logout</button>
                    </template>
                    <template v-else>
                        <button type="button" @click="loginScWallet" class="p-1">Login</button>
                    </template>
                </div>
            </template>
        </div>
        <div class="grid align-items-center m-0">
            <div class="col-12 sm:col-3">
                Source:
            </div>
            <div class="col-12 sm:col-9 flex">
                <select v-model="contract.source" class="m-0 p-1">
                    <template v-for="cs in contractSources">
                        <option :value="cs">
                            {{cs}}
                        </option>
                    </template>
                </select>
            </div>
        </div>
        <div class="grid align-items-center m-0">
            <div class="col-12 sm:col-3"></div>
            <div class="col-12 sm:col-9 flex">
                <button @click="compile" class="p-1">Compile</button>
            </div>
        </div>

        <div class="grid align-items-start m-0" v-if="contract.status === 'compiled'">
            <div class="col-12 sm:col-3">
                <div>Compiled code:</div>
                <div>
                    <button @click="getSource" class="p-1 w-auto">Source</button>
                </div>
            </div>
            <div class="col-12 sm:col-9 flex">
                <textarea v-if="contract.phar_code" class="p-1 m-0">{{contract.phar_code}}</textarea>
            </div>
        </div>

        <template v-if="contract.status === 'compiled' || contract.status==='deployed'">
            <div class="grid align-items-center m-0">
                <div class="col-12 sm:col-3">
                    Name:
                </div>
                <div class="col-12 sm:col-9">
                    <input type="text" class="m-0 p-1" v-model="contract.name" :disabled="contract.status === 'deployed'"/>
                </div>
            </div>
            <div class="grid align-items-center m-0">
                <div class="col-12 sm:col-3">
                    Description:
                </div>
                <div class="col-12 sm:col-9">
                    <input type="text" class="m-0 p-1" v-model="contract.description" :disabled="contract.status === 'deployed'"/>
                </div>
            </div>
            <div class="grid align-items-center m-0">
                <div class="col-12 sm:col-3">
                    Metadata:
                </div>
                <div class="col-12 sm:col-9">
                    <input type="text" class="m-0 p-1" v-model="contract.metadata" :disabled="contract.status === 'deployed'"/>
                </div>
            </div>
            <div class="grid align-items-center m-0">
                <div class="col-12 sm:col-3">
                    Amount:
                </div>
                <div class="col-12 sm:col-9">
                    <input type="text" class="m-0 p-1" v-model="contract.amount" :disabled="contract.status === 'deployed'"/>
                </div>
            </div>
            <div class="grid align-items-center m-0" v-if="contract.interface">
                <div class="col-12 sm:col-3">
                    Params:
                </div>
                <div class="col-12 sm:col-9 grid flex-column m-0">
                    <template v-for="param in contract.interface.deploy.params">
                        <div class="grid flex-grow-1 align-items-center">
                            <div class="col-6 sm:col-3">
                                {{param.name}}
                            </div>
                            <div class="col-6 sm:col-9 flex">
                                <input type="text" class="m-0 p-1" v-model="deployParams[param.name]" :disabled="contract.status === 'deployed'"/>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="grid align-items-center m-0">
                <div class="col-12 sm:col-3">
                    Signature:
                </div>
                <div class="col-12 sm:col-9 flex">
                    <input class="m-0 p-1" type="text" v-model="contract.signature" v-if="contract.status === 'deployed'" disabled/>
                </div>
            </div>
        </template>

        <div class="grid align-items-center grid-nogutter" v-if="contract.status === 'compiled'">
            <div class="col-12 sm:col-3"></div>
            <div class="col-12 sm:col-9 flex">
                <button @click="deploy" :disabled="contract.status=== 'deployed'">Deploy</button>
            </div>
        </div>

        <template v-if="contract.status=== 'deployed'">
            <h4 class="p-2 border-top-1">Interface</h4>
            <div class="grid if-tabs mx-2 mt-2">
                <div :class="`col p-0 text-center if-tab ${interfaceTab === 'methods' ? 'sel-tab' : ''}`">
                    <a href="" @click.prevent="interfaceTab = 'methods'">Methods</a>
                </div>
                <div :class="`col p-0 text-center if-tab ${interfaceTab === 'views' ? 'sel-tab' : ''}`">
                    <a href="" @click.prevent="interfaceTab = 'views'">Views</a>
                </div>
                <div :class="`col p-0 text-center if-tab ${interfaceTab === 'properties' ? 'sel-tab' : ''}`">
                    <a href="" @click.prevent="interfaceTab = 'properties'">Properties</a>
                </div>
            </div>
            <div v-if="interfaceTab==='methods' && contract.interface.methods" class="border-1 mx-2 mb-2">
                <div class="grid align-items-center m-0">
                    <div class="col-12 sm:col-3 p-1">Type:</div>
                    <div class="col-12 sm:col-9 p-1 flex">
                        <label>
                            <input type="radio" v-model="methodType" value="exec" style="width: auto"/>
                            Exec
                        </label>
                        <label class="ml-2">
                            <input type="radio" v-model="methodType" value="send" style="width: auto"/>
                            Send
                        </label>
                    </div>
                </div>
                <div class="grid align-items-center m-0">
                    <div class="col-12 sm:col-3 p-1">Address:</div>
                    <div class="col-12 sm:col-9 p-1">
                        <input type="text" v-model="methodAddress" class="m-0 p-1"  readonly v-if="methodType === 'exec'"/>
                        <div class="flex" style="gap: 5px" v-if="methodType === 'send'">
                            <select v-model="sendAddress" class="m-0 p-1">
                                <template v-for="account in Object.values(accounts)">
                                    <option :value="account.address">
                                        {{account.address}}
                                    </option>
                                </template>
                            </select>
                            <input type="text" class="m-0 p-1" v-model="sendAddress"/>
                            <button @click="generateSendAddress" class="p-1">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="grid align-items-center m-0">
                    <div class="col-12 sm:col-3 p-1">Amount:</div>
                    <div class="col-12 sm:col-9 p-1">
                        <input type="text" v-model="methodAmount" class="m-0 p-1"/>
                    </div>
                </div>
                <template v-for="method in contract.interface.methods">
                    <div class="grid border-1 m-1 p-1 align-items-center">
                        <div class="col-12 sm:col-3 p-1">
                            <button class="w-full m-0 p-1" @click="callMethod(method.name)">{{method.name}}</button>
                        </div>
                        <div class="col-12 sm:col-9 p-1">
                            {{initMethodParamMap(method.name)}}
                            <template v-for="param in method.params">
                                <div class="grid align-items-center m-0 p-0">
                                    <div class="col-12 sm:col-3 p-1">
                                        {{param.name}}
                                        <span v-if="param.required">*</span>
                                    </div>
                                    <div class="col-12 sm:col-9 p-1">
                                        <input type="text" v-model="methodParams[method.name][param.name]" :placeholder="param.value" class="m-0 p-1"/>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
            <div v-if="interfaceTab==='views' && contract.interface.views" class="border-1 mx-2 mb-2">
                <template v-for="view in contract.interface.views">
                    <div class="grid border-1 m-1 p-1 align-items-center">
                        <div class="col-12 sm:col-3 p-1">
                            <button class="w-full m-0 p-1"  @click="callView(view.name)">{{view.name}}</button>
                        </div>
                        <div class="col-12 sm:col-6 p-1">
                            {{initViewParamMap(view.name)}}
                            <template v-for="param in view.params">
                                <div class="grid align-items-center m-0 p-0">
                                    <div class="col-12 sm:col-3 p-1">
                                        {{param.name}}
                                        <span v-if="param.required">*</span>
                                    </div>
                                    <div class="col-12 sm:col-9 p-1">
                                        <input type="text" v-model="viewParams[view.name][param.name]" :placeholder="param.value" class="m-0 p-1"/>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="col-12 sm:col-3 p-1 flex align-items-center flex-wrap" v-if="viewResponses[view.name]">
                            <div class="flex-1" style="white-space: nowrap; overflow: auto">{{viewResponses[view.name]}}</div>
                            <button @click="delete viewResponses[view.name]" class="p-1">Clear</button>
                        </div>
                    </div>
                </template>
            </div>
            <div v-if="interfaceTab==='properties' && contract.interface.properties" class="border-1 mx-2 mb-2">
                <template v-for="property in contract.interface.properties">
                    <div class="grid border-1 m-1 p-1 align-items-center">
                        <div class="col-12 sm:col-3 p-1">
                            <button @click="getProperty(property.name)" class="w-full m-0 p-1">{{property.name}}</button>
                        </div>
                        <div  class="col-12 sm:col-3 p-1" v-if="property.type === 'map'">
                            <input type="text" v-model="propertyKeys[property.name]" class="m-0 p-1"/>
                        </div>
                        <div class="col-12 sm:col-6 p-1 flex align-items-center" v-if="propertyValues[property.name]">
                            <div class="flex-1">{{propertyValues[property.name]}}</div>
                            <button @click="delete propertyValues[property.name]" class="p-1 m-0">Clear</button>
                        </div>
                    </div>
                </template>
            </div>
        </template>

    <div class="grid if-tabs mx-2 mt-2">
        <div :class="`p-0 col text-center if-tab ${outTab === 1 ? 'sel-tab' : ''} `">
            <a href="#" @click.prevent="outTab = 1">Transactions</a>
        </div>
        <div :class="`p-0 col text-center if-tab ${outTab === 2 ? 'sel-tab' : ''} `">
            <a href="#" @click.prevent="outTab = 2">State</a>
        </div>
        <div :class="`p-0 col text-center if-tab ${outTab === 3 ? 'sel-tab' : ''} `">
            <a href="#" @click.prevent="outTab = 3">Debug</a>
        </div>
    </div>
    <div class="border-1 mx-2 mb-2" v-if="outTab === 1">
        <div style="overflow-x: auto; scrollbar-color: auto; scrollbar-width: auto; max-height: 30vh;">
            <table class="table table-striped table-condensed">
                <thead>
                    <tr>
                        <th>height</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Fee</th>
                        <th>Method</th>
                        <th>Params</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(tx,ix) in transactions">
                        <td>
                            <template v-if="virtual">{{ix}}</template>
                            <template v-else>
                                <template v-if="!tx.block">
                                    <a :href="`${node}/apps/explorer/tx.php?id=${tx.id}`" target="_blank">mempool</a>
                                </template>
                                <template v-else>
                                    <a :href="`${node}/apps/explorer/tx.php?id=${tx.id}`" target="_blank">{{tx.height}}</a>
                                </template>
                            </template>
                        </td>
                        <td>
                            <a :href="`${node}/apps/explorer/address.php?address=${tx.src}`" target="_blank">{{tx.src}}</a>
                        </td>
                        <td>
                            <a :href="`${node}/apps/explorer/address.php?address=${tx.dst}`" target="_blank">{{tx.dst}}</a>
                        </td>
                        <td>
                            {{txType(tx)}}
                        </td>
                        <td>{{tx.val}}</td>
                        <td>{{tx.fee}}</td>
                        <td>{{txMethod(tx)}}</td>
                        <td>{{txParams(tx)}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="border-1 mx-2 mb-2" v-if="outTab === 2">
        <table>
            <tr v-for="(val, key) in state">
                <td>{{key}}</td>
                <td>
                    <template v-if="typeof val === 'object'">
                        <table>
                            <tr v-for="(val1, key1) in val">
                                <td>{{key1}}</td>
                                <td>{{val1}}</td>
                            </tr>
                        </table>
                    </template>
                    <template v-else>
                        {{val}}
                    </template>
                </td>
            </tr>
        </table>
    </div>
    <div class="border-1 mx-2 mb-2 p-2" v-if="outTab === 3">
        {{debug_logs}}
    </div>

</div>
<div class="clearfix"></div>
<link rel="stylesheet" href="https://unpkg.com/primeflex@3.1.2/primeflex.css">
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://unpkg.com/vue-async-computed@4.0.1"></script>
<script src="/phpcoin/app.js" type="text/javascript"></script>
<script src="https://node1.phpcoin.net/apps/common/js/phpcoin-crypto.js" type="text/javascript"></script>
<link rel="stylesheet" href="phpcoin/style.css"/>
<style>
    #SBRIGHT {
        width: <?php echo @$settings['sidebars.sb-right-width'] ?>;
    }
    #SBLEFT {
        width: <?php echo @$settings['sidebars.sb-left-width'] ?>;
    }
    #SBRIGHT:not(.drag) {
        user-select: initial !important;
    }
    .word-break{
        word-break: break-word;
        white-space: break-spaces;
    }
</style>
<style>
    .if-tabs .sel-tab {
        background-color: var(--fontColorMajor);
        color: #fff;
    }
    .if-tabs .if-tab > a {
        text-decoration: none;
        color: var(--fontColorMajor);
    }
    .if-tabs .sel-tab > a {
        color: #fff;
        text-decoration: none;
    }
    .if-tabs .if-tab:hover {
        background-color: #ccc;
    }
    .if-tabs .if-tab > a:hover{
        background-color: transparent !important;
        text-decoration: none;
        color: var(--fontColorMajor);
    }
    #SBRIGHT a:hover {
        background-color: transparent !important;
    }
    #last_login {
        display: none !important;
    }
    #SBRIGHT {
        user-select: auto !important;
    }
    table a {
        text-decoration: none;
    }
    table a:hover {
        text-decoration: underline;
    }
</style>
