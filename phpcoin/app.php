<div id="app">

    <div class="flex flex-wrap align-items-center align-content-between">
        <div class="">
            <h4>Smart Contract actions</h4>
        </div>
        <div class="ml-auto">
            <button name="save" @click="save" class="p-1">Save</button>
            <button name="reset" @click="reset" class="p-1">Reset</button>
            <button name="refresh" @click="refresh" class="p-1">Refresh</button>
        </div>
    </div>
    <hr/>
    <div class="grid align-items-center grid-nogutter">
        <div class="col-12 sm:col-3">Engine:</div>
        <div class="col-12 sm:col-9">
            <select class="p-1" v-model="engine">
                <template v-for="e, k in engines" :key="k">
                    <option :value="k">{{e.name}}</option>
                </template>
            </select>
        </div>
    </div>

    <template v-if="virtual">
        <div class="grid align-items-center grid-nogutter">
            <div class="col-12 sm:col-3">
                Wallet address:
            </div>
            <div class="col-12 sm:col-9">
                <select v-model="address">
                    <template v-for="account in accounts">
                        <option :value="account.address">
                            {{account.address}}
                            <template v-if="contract && account.address === contract.address">(SmartContract)</template>
                        </option>
                    </template>
                </select>
            </div>
            <div class="col-12 sm:col-3">
                Private key:
            </div>
            <div class="col-12 sm:col-9">
                <input type="text" value="<?php echo $private_key ?>" class="p-1" name="private_key" <?php if($virtual) { ?>readonly="readonly"<?php } ?>/>
            </div>
            <div class="col-12 sm:col-3">
                Balance:
            </div>
            <div class="col-12 sm:col-9 flex flex-wrap">
                <div><?php echo num($balance) ?></div>
                <div>(<?php echo $_SESSION['account']['address'] ?>)</div>
            </div>
        </div>
    </template>
    <template v-else>

    </template>

</div>
<script type="text/javascript">
    const engines = <?php echo json_encode($engines); ?>;
</script>
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="/phpcoin/app.js" type="text/javascript"></script>
<style>
    #app {
        border: 1px solid red;
    }
</style>
