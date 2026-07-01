const password=document.querySelector("input[type=password]");

password.addEventListener("keydown",function(e){

    if(e.key==="Enter"){

        this.form.submit();

    }

});