function showToast(message, code) {
    const toast = document.createElement("div");
    toast.textContent = message;
    toast.style.position = "fixed";
    toast.style.bottom = "20px";
    toast.style.right = "20px";
    console.log(code);
    if(code=='success'){
        toast.style.background = "#1096049f";
        toast.style.color = "#ffffffff";
    }
    else if(code=='error'){
        toast.style.background = "#ff000052";
        toast.style.color = "#ffffffff";
    }
    else if(code=='warning'){
        toast.style.background = "#deee06ff";
        toast.style.color = "#333333ff";
    }
    else{
        toast.style.background = "#333";
        toast.style.color = "#fff";
    }

    toast.style.padding = "10px";
    toast.style.borderRadius = "5px";
    toast.style.opacity = "0.9";
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2000);
}
