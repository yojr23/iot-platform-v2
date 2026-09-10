import {createApp,ref,nextTick} from 'vue';
import {createPinia} from 'pinia';
import {describe,it,expect,vi,afterEach,beforeEach} from 'vitest';
import {useAuthStore} from '@/stores/auth';
import {useLabWorkspace} from './useLabWorkspace';
const api=vi.hoisted(()=>({load:vi.fn(),save:vi.fn()}));
vi.mock('@/api/dashboard',()=>({getDashboardPreferences:api.load,updateDashboardPreferences:api.save}));
vi.mock('@/stores/graphSeriesQuery',()=>({useGraphSeriesQueryStore:()=>({fetchWindow:vi.fn(async()=>null),resultForQuery:()=>null})}));
vi.mock('@/realtime/useSensorRealtime',()=>({useSensorRealtime:()=>({subscribeSensor:vi.fn(),unsubscribeSensor:vi.fn()})}));
const apps=[];
async function mount(authenticated=false){let state;const pinia=createPinia();const auth=useAuthStore(pinia);if(authenticated){auth.token='test';auth.user={id:1};}const app=createApp({setup(){state=useLabWorkspace(ref([{id:1,name:'Device',sensors:[{id:1,name:'Temperature',unit:'C'},{id:2,name:'Pressure',unit:'bar'}]}]));return()=>null;}});app.use(pinia);app.mount(document.createElement('div'));apps.push(app);await nextTick();await Promise.resolve();return state;}
beforeEach(()=>{window.localStorage.clear();api.load.mockResolvedValue({data:{layout:null}});api.save.mockResolvedValue({data:{}});});
afterEach(()=>{apps.splice(0).forEach(a=>a.unmount());vi.clearAllMocks();});
describe('Lab workspace explicit persistence',()=>{
 it('keeps guest edits in memory and never requests or saves preferences',async()=>{const s=await mount();s.remove(s.selectedId.value);await s.save();expect(api.load).not.toHaveBeenCalled();expect(api.save).not.toHaveBeenCalled();expect(s.dirty.value).toBe(true);s.undo();expect(s.widgets.value).toHaveLength(2);});
 it('marks only submitted edits saved and preserves edits made during a request',async()=>{const s=await mount(true);s.changeRange('1h');let resolve;api.save.mockImplementationOnce(()=>new Promise(r=>resolve=r));const saving=s.save();s.changeRange('6h');resolve({data:{}});await saving;expect(s.saveState.value).toBe('dirty');expect(s.dirty.value).toBe(true);await s.save();expect(s.saveState.value).toBe('saved');expect(s.dirty.value).toBe(false);});
 it('keeps the draft and shows an error when saving fails',async()=>{const s=await mount(true);s.remove(s.selectedId.value);api.save.mockRejectedValueOnce(new Error('offline'));await s.save();expect(s.widgets.value).toHaveLength(1);expect(s.saveState.value).toBe('error');expect(s.dirty.value).toBe(true);s.undo();expect(s.widgets.value).toHaveLength(2);});
 it('allows removing the final widget and restores its stable ID on Undo',async()=>{const s=await mount();s.widgets.value.slice().forEach(w=>s.remove(w.id));expect(s.selected.value).toBe(null);const id=s.removed.value.widget.id;s.undo();expect(s.selected.value.id).toBe(id);});
});
