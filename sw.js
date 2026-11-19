const CACHE_NAME='sigap-mpa-v1.11.3-pantun1';
const APP_SHELL=[
  './','index.html','styles.css','sigap-v08.css','app.js','sigap-v08.js','xlsx-export.js',
  'manifest.webmanifest','assets/sigap-logo-full.png','assets/sigap-logo.png','assets/sigap-mark.png',
  'icons/favicon-32.png','icons/icon-192.png','icons/icon-512.png',
  'icons/icon-maskable-192.png','icons/icon-maskable-512.png',
  'icons/apple-touch-icon.png','icons/apple-touch-icon-180.png','icons/apple-touch-icon-167.png','icons/apple-touch-icon-152.png','icons/topbar-logo.png','assets/sigap-clock-g.png'
];
self.addEventListener('message',event=>{if(event.data?.type==='SKIP_WAITING')self.skipWaiting();});
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE_NAME).then(cache=>cache.addAll(APP_SHELL)).catch(()=>null).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',event=>event.waitUntil((async()=>{
  const keys=await caches.keys();
  await Promise.all(keys.filter(k=>k!==CACHE_NAME).map(k=>caches.delete(k)));
  await self.clients.claim();
})()));
self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET')return;
  const url=new URL(req.url);
  if(url.origin!==self.location.origin||url.pathname.startsWith('/api'))return;
  if(req.mode==='navigate'){
    event.respondWith(fetch(req,{cache:'no-store'}).then(res=>{
      const copy=res.clone();caches.open(CACHE_NAME).then(c=>c.put('index.html',copy));return res;
    }).catch(()=>caches.match('index.html')));
    return;
  }
  event.respondWith(caches.match(req).then(cached=>cached||fetch(req,{cache:'no-store'}).then(res=>{
    if(res.ok){const copy=res.clone();caches.open(CACHE_NAME).then(c=>c.put(req,copy));}
    return res;
  })));
});
