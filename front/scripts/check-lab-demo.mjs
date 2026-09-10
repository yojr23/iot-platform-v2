import assert from 'node:assert/strict';
import {createServer} from 'vite';
import WebSocket from 'ws';
const server=await createServer({mode:'demo',server:{host:'127.0.0.1',port:5176}});
await server.listen();
const base='http://127.0.0.1:5176';
try{
 const catalog=await (await fetch(base+'/api/public/graph/bootstrap')).json();assert.equal(catalog.data.devices.length,3);
 assert.equal((await fetch(base+'/api/alerts/active')).status,401);
 assert.equal((await fetch(base+'/api/public/graph/sensors/999/series')).status,404);
 const to=new Date(),from=new Date(to-300000);const series=await(await fetch(`${base}/api/public/graph/sensors/1/series?from=${from.toISOString()}&to=${to.toISOString()}`)).json();assert.equal(series.data.points.length,150);assert.equal(series.data.stats.count,150);
 const login=await(await fetch(base+'/api/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:'demo@sinoa.local',password:'local-preview'})})).json();const headers={Authorization:`Bearer ${login.access_token}`};assert.equal((await fetch(base+'/api/alerts/active',{headers})).status,200);
 const ws=new WebSocket('ws://127.0.0.1:5176/app/local-preview');
 await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('No WebSocket reading received')),7000);ws.on('error',reject);ws.on('message',raw=>{const m=JSON.parse(raw);if(m.event==='pusher:connection_established')ws.send(JSON.stringify({event:'pusher:subscribe',data:{channel:'sensor.1'}}));if(m.event==='App\\Events\\NewSensorReading'){const p=JSON.parse(m.data);assert.equal(p.sensor_id,1);assert.equal(typeof p.value,'number');clearTimeout(timer);resolve();}});});ws.close();console.log('PASS: graph catalog, half-open 5m series, guest denial, private session, live WebSocket delivery.');
}catch(e){console.error(e);process.exitCode=1;}finally{await server.close();process.exit(process.exitCode||0);}
