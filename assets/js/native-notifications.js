/* Web + Capacitor notification bridge. The web app keeps an in-app centre;
   native builds can additionally schedule Local Notifications on the device. */
(function(){
  const btn=document.getElementById('enableReminders'), status=document.getElementById('reminderStatus');
  if(!btn) return;
  const csrf=(document.querySelector('input[name="csrf"]')||{}).value||'';
  const setStatus=t=>{if(status)status.textContent=t;};
  async function native(){
    const cap=window.Capacitor, plugins=cap&&cap.Plugins;
    if(!plugins) return false;
    const local=plugins.LocalNotifications;
    if(!local) return false;
    try{
      const p=await local.requestPermissions();
      if(p.display && p.display!=='granted') return false;
      const r=await fetch('api/notifications.php',{credentials:'same-origin',cache:'no-store'});
      const data=await r.json();
      const pending=(data.scheduled||[]).filter(n=>n.scheduledAt);
      if(pending.length){
        await local.schedule({notifications:pending.map((n,i)=>({id:10000+(Number(n.notificationID)||i),title:n.title,body:n.message,schedule:{at:new Date(n.scheduledAt.replace(' ','T'))},extra:{url:n.actionURL||'notifications.php'}}))});
      }
      return true;
    }catch(e){return false;}
  }
  btn.addEventListener('click',async()=>{
    btn.disabled=true; setStatus('Requesting permission…');
    let ok=await native();
    if(!ok && 'Notification' in window){
      try{const p=Notification.permission==='default'?await Notification.requestPermission():Notification.permission;ok=p==='granted';}catch(e){}
    }
    if(ok){
      try{await fetch('api/notifications.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=enable&csrf='+encodeURIComponent(csrf),credentials:'same-origin'});}catch(e){}
      setStatus('Reminders are enabled on this device.'); btn.textContent='🔔 Reminders enabled';
    }else{setStatus('Notifications were not enabled. You can change permission in your device/browser settings.');btn.disabled=false;}
  });
})();
