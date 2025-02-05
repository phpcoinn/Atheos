function onSubmit(event) {
    console.log(event)
    let formData = $("form").serialize()
    let submitter = $(event.submitter)
    formData += '&' + submitter.attr('name') + '=' + submitter.val()
    event.preventDefault();

    $("#SBRIGHT #phpcoin-sb").css('opacity', 0.5)

    echo({
        url: '/phpcoin-sb.php',
        data: {
            formData: formData,
            ajax: true
        },
        settled: function(reply, status) {

            if(reply.startsWith('json:')) {
                let json = reply.substring(5);
                let response = JSON.parse(json)
                if(response.action === 'redirect') {
                    let url = response.url
                    document.location.href = url
                }
                if(response.action === 'message') {
                    alert(response.message.text)
                    $("#SBRIGHT #phpcoin-sb").css('opacity', 1)
                }
                return
            }

            let tmp = $('<div style="display:none"><div>')
            tmp.html(reply)
            $("body").append(tmp)
            let sb = tmp.find("#phpcoin-sb")
            let h = sb.html()
            tmp.remove()
            $("#SBRIGHT #phpcoin-sb").html(h)
            $("#SBRIGHT #phpcoin-sb").css('opacity', 1)
            // if (status !== 'success') toast(status, reply);
            // if (hidden) return;
            // // self.displayStatus(reply);
            // toast(status, 'Setting "' + key + '" saved.');

        }
    });

    if($(event.submitter).data("call")) {
        //let method = $(event.submitter).data("call")
        //let params = []
        //if($("form [name='params["+method+"]']").length === 1) {
        //    params =  $("form [name='params["+method+"]']").val().trim()
        //    params = params.split(",")
        //    params = params.map(function (item) { return item.trim() })
        //    console.log(params)
        //}
        //let msg = JSON.stringify({method, params})
        //msg = btoa(msg)
        //let privateKey = $("form [name=private_key]").val().trim()
        //let amount = $("[name=amount").val();
        //signTx(privateKey, amount, <?php //echo TX_SC_EXEC_FEE ?>//, msg, <?php //echo TX_TYPE_SC_EXEC ?>//);
    }
}

function signTx(privateKey, amount, fee, msg, type) {
    amount = Number(amount).toFixed(8)
    fee = Number(fee).toFixed(8)
    let dst = $("form [name=sc_address]").val().trim()
    let date = Math.round(new Date().getTime()/1000)
    let data = '<?php echo CHAIN_ID ?>' + amount + '-' + fee + '-' + dst + '-' + msg + '-' + type + '-'
        + '<?php echo @$public_key ?>' + '-' + date
    let sig = sign(data, privateKey)
    console.log(data, sig)
    $("form [name=signature]").val(sig)
    $("form [name=msg]").val(msg)
    $("form [name=date]").val(date)
}


function showInterfaceTab(el, name) {
    $(".tab").hide();
    $(".if-tab").removeClass("sel-tab");
    $(el).parent().addClass("sel-tab");
    $(".tab[name="+name+"]").show();
}

function newContractAddress() {
    let account = generateAccount();
    console.log(account)
    $("[name=deploy_address]").val(account.address)
    localStorage.setItem("pk_"+account.address, account.privateKey)
}
