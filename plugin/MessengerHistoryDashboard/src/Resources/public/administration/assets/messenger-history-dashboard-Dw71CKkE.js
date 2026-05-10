const v="modulepreload",b=function(e){return window.__sw__.assetPath+"/bundles/messengerhistorydashboard/administration/"+e},h={},w=function(t,a,p){let m=Promise.resolve();if(a&&a.length>0){let l=function(i){return Promise.all(i.map(o=>Promise.resolve(o).then(c=>({status:"fulfilled",value:c}),c=>({status:"rejected",reason:c}))))};document.getElementsByTagName("link");const s=document.querySelector("meta[property=csp-nonce]"),n=(s==null?void 0:s.nonce)||(s==null?void 0:s.getAttribute("nonce"));m=l(a.map(i=>{if(i=b(i),i in h)return;h[i]=!0;const o=i.endsWith(".css"),c=o?'[rel="stylesheet"]':"";if(document.querySelector(`link[href="${i}"]${c}`))return;const r=document.createElement("link");if(r.rel=o?"stylesheet":v,o||(r.as="script"),r.crossOrigin="",r.href=i,n&&r.setAttribute("nonce",n),document.head.appendChild(r),o)return new Promise((u,g)=>{r.addEventListener("load",u),r.addEventListener("error",()=>g(new Error(`Unable to preload CSS for ${i}`)))})}))}function d(l){const s=new Event("vite:preloadError",{cancelable:!0});if(s.payload=l,window.dispatchEvent(s),!s.defaultPrevented)throw l}return m.then(l=>{for(const s of l||[])s.status==="rejected"&&d(s.reason);return t().catch(d)})},f=Shopware.Classes.ApiService;class y extends f{constructor(t,a){super(t,a,"mh-dashboard")}listMessages({status:t=null,searchTerm:a="",page:p=1,limit:m=25,topicGroup:d="",createdFrom:l="",createdTo:s=""}={}){const n=new URLSearchParams;t&&n.set("status",t),a&&n.set("query",a),n.set("page",p),n.set("limit",m),d&&n.set("topicGroup",d),l&&n.set("createdFrom",l),s&&n.set("createdTo",s);const i=n.toString();return this.httpClient.get(`/_action/mh/messages${i?`?${i}`:""}`,{headers:this.getHeaders()})}loadMetrics(){return this.httpClient.get("/_action/mh/metrics",{headers:this.getHeaders()})}getMessageDetail(t){return this.httpClient.get(`/_action/mh/messages/${encodeURIComponent(t)}`,{headers:this.getHeaders()})}getHeaders(){return{Accept:"application/json",Authorization:`Bearer ${this.loginService.getToken()}`,"Content-Type":"application/json"}}retryMessage(t,a="manual retry"){return this.httpClient.post(`/_action/mh/messages/${encodeURIComponent(t)}/retry`,{reason:a},{headers:this.getHeaders()})}quarantineMessage(t,a="manual quarantine"){return this.httpClient.post(`/_action/mh/messages/${encodeURIComponent(t)}/quarantine`,{reason:a},{headers:this.getHeaders()})}dismissMessage(t,a="manual dismiss"){return this.httpClient.post(`/_action/mh/messages/${encodeURIComponent(t)}/dismiss`,{reason:a},{headers:this.getHeaders()})}cleanupMessages(t=30){return this.httpClient.post("/_action/mh/cleanup",{days:t},{headers:this.getHeaders()})}}Shopware.Service().register("mhDashboardApiService",()=>new y(Shopware.Application.getContainer("init").httpClient,Shopware.Service("loginService")));const{Component:x}=Shopware;x.register("mh-dashboard-index",{inject:["mhDashboardApiService"],mixins:[Shopware.Mixin.getByName("notification")],template:`
        <sw-page class="mh-dashboard-index">
            <template #smart-bar-header>
                <h2>Messenger Audit</h2>
            </template>

            <template #content>
                <sw-card title="Metrics">
                    <sw-button @click="loadAll">Neu laden</sw-button>

                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:16px;">
                        <sw-card title="Total"><strong>{{ metrics.total || 0 }}</strong></sw-card>
                        <sw-card title="Failed"><strong>{{ metrics.failed || 0 }}</strong></sw-card>
                        <sw-card title="Handled"><strong>{{ metrics.handled || 0 }}</strong></sw-card>
                        <sw-card title="Received"><strong>{{ metrics.received || 0 }}</strong></sw-card>
                    </div>
                </sw-card>

                <sw-card title="Messages" style="margin-top:16px;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                        <sw-button
                            v-for="option in topicGroupOptions"
                            :key="option.value || 'all'"
                            size="small"
                            :variant="selectedTopicGroup === option.value ? 'primary' : 'secondary'"
                            @click="selectTopicGroup(option.value)">
                            {{ option.label }}
                        </sw-button>
                    </div>

                    <div style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(160px,1fr) minmax(220px,1.2fr) minmax(120px,0.8fr);gap:16px;align-items:end;margin-bottom:16px;">
                        <sw-text-field
                            v-model:value="searchTerm"
                            label="Suche"
                            placeholder="Nachricht suchen">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedStatus"
                            label="Status"
                            :options="statusOptions">
                        </sw-single-select>

                        <sw-single-select
                            v-model:value="selectedTimeRange"
                            label="Zeitraum"
                            :options="timeRangeOptions">
                        </sw-single-select>

                        <sw-single-select
                            v-model:value="selectedLimit"
                            label="Pro Seite"
                            :options="limitOptions">
                        </sw-single-select>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                        <sw-button variant="primary" @click="applyFilters">Filter anwenden</sw-button>
                    </div>

                    <div style="display:grid;grid-template-columns:minmax(220px,1fr) auto auto;gap:16px;align-items:end;margin-bottom:16px;padding:16px;border:1px solid #d1d9e0;border-radius:8px;background:#f8fafc;">
                        <div style="color:#52667a;font-size:14px;">
                            {{ total }} Einträge im aktuellen Filter
                        </div>

                        <sw-single-select
                            v-model:value="cleanupDays"
                            label="Einträge löschen älter als"
                            :options="cleanupOptions">
                        </sw-single-select>

                        <sw-button variant="danger" @click="cleanupOldEntries">Löschen</sw-button>
                    </div>

                    <sw-data-grid
                        v-if="messages.length > 0"
                        :data-source="messages"
                        :columns="columns"
                        :show-selection="false">
                        <template #column-available_actions="{ item }">
                            {{ item.available_actions }}
                        </template>

                        <template #column-status="{ item }">
                            {{ item.status_label }}
                        </template>

                        <template #actions="{ item }">
                            <sw-context-menu-item
                                :router-link="{ name: 'mh.dashboard.detail', params: { id: item.id } }">
                                Details
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="item.status !== 'failed'"
                                @click="runAction('retry', item)">
                                Erneut senden
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!['failed', 'received', 'dispatched'].includes(item.status)"
                                @click="runAction('quarantine', item)">
                                Ausblenden
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!['failed', 'received', 'dispatched', 'handled'].includes(item.status)"
                                @click="runAction('dismiss', item)">
                                Erledigen
                            </sw-context-menu-item>
                        </template>

                        <template #pagination>
                            <sw-pagination
                                :page="page"
                                :total="total"
                                :limit="selectedLimit"
                                :auto-hide="false"
                                @page-change="onPageChange">
                            </sw-pagination>
                        </template>
                    </sw-data-grid>

                    <div v-else style="margin-top:16px;">
                        Keine Daten vorhanden.
                    </div>
                </sw-card>
            </template>
        </sw-page>
    `,data(){return{messages:[],metrics:{},isLoading:!1,total:0,page:1,searchTerm:"",selectedTopicGroup:"",selectedStatus:"",selectedTimeRange:"30d",selectedLimit:25,cleanupDays:30,topicGroupOptions:[{value:"",label:"Alle Bereiche"},{value:"Bestellungen",label:"Bestellungen"},{value:"Zahlungen",label:"Zahlungen"},{value:"System",label:"System"}],statusOptions:[{value:"",label:"Alle"},{value:"dispatched",label:"Gesendet"},{value:"received",label:"Empfangen"},{value:"handled",label:"Verarbeitet"},{value:"failed",label:"Fehlgeschlagen"}],timeRangeOptions:[{value:"",label:"Gesamter Zeitraum"},{value:"1d",label:"Letzte 24 Stunden"},{value:"7d",label:"Letzte 7 Tage"},{value:"30d",label:"Letzte 30 Tage"},{value:"90d",label:"Letzte 90 Tage"}],limitOptions:[{value:10,label:"10"},{value:25,label:"25"},{value:50,label:"50"},{value:100,label:"100"}],cleanupOptions:[{value:7,label:"Älter als 7 Tage"},{value:30,label:"Älter als 30 Tage"},{value:90,label:"Älter als 90 Tage"},{value:180,label:"Älter als 180 Tage"}],columns:[{property:"created_at",label:"Erstellt",primary:!0},{property:"message_type",label:"Typ"},{property:"status",label:"Status"},{property:"retry_count",label:"Versuche"},{property:"available_actions",label:"Mögliche Aktion"}]}},created(){this.loadAll()},methods:{async loadAll(){this.isLoading=!0;try{const e=this.resolveTimeRange(),[t,a]=await Promise.all([this.mhDashboardApiService.listMessages({status:this.selectedStatus,searchTerm:this.searchTerm.trim(),page:this.page,limit:this.selectedLimit,topicGroup:this.selectedTopicGroup,createdFrom:e.createdFrom,createdTo:e.createdTo}),this.mhDashboardApiService.loadMetrics()]);this.messages=t.data.data||[],this.total=t.data.total||0,this.metrics=a.data||{}}catch{this.messages=[],this.metrics={},this.total=0,this.createNotificationError({title:"Messenger Audit",message:"Die Messenger-Daten konnten nicht geladen werden."})}finally{this.isLoading=!1}},async runAction(e,t){try{e==="retry"&&await this.mhDashboardApiService.retryMessage(t.id),e==="quarantine"&&await this.mhDashboardApiService.quarantineMessage(t.id),e==="dismiss"&&await this.mhDashboardApiService.dismissMessage(t.id),await this.loadAll()}catch{this.createNotificationError({title:"Messenger Audit",message:"Die Aktion konnte nicht ausgeführt werden."})}},onPageChange(e){this.page=e,this.loadAll()},selectTopicGroup(e){this.selectedTopicGroup=e,this.page=1,this.loadAll()},applyFilters(){this.page=1,this.loadAll()},resolveTimeRange(){if(!this.selectedTimeRange)return{createdFrom:"",createdTo:""};const e=new Date,t=new Date(e);return this.selectedTimeRange==="1d"&&t.setDate(e.getDate()-1),this.selectedTimeRange==="7d"&&t.setDate(e.getDate()-7),this.selectedTimeRange==="30d"&&t.setDate(e.getDate()-30),this.selectedTimeRange==="90d"&&t.setDate(e.getDate()-90),{createdFrom:t.toISOString().slice(0,10),createdTo:e.toISOString().slice(0,10)}},async cleanupOldEntries(){try{const t=(await this.mhDashboardApiService.cleanupMessages(this.cleanupDays)).data.deleted||{};this.createNotificationSuccess({title:"Messenger Audit",message:`${t.messages||0} Nachrichten wurden bereinigt.`}),this.page=1,await this.loadAll()}catch{this.createNotificationError({title:"Messenger Audit",message:"Die Bereinigung konnte nicht ausgeführt werden."})}}}});const{Component:A}=Shopware;A.register("mh-dashboard-failed",{inject:["mhDashboardApiService"],mixins:[Shopware.Mixin.getByName("notification")],template:`
        <sw-page class="mh-dashboard-failed">
            <template #smart-bar-header>
                <h2>Fehlgeschlagene Nachrichten</h2>
            </template>

            <template #content>
                <sw-card title="Fehlgeschlagene Nachrichten">
                    <div style="display:grid;grid-template-columns:minmax(220px,2fr) minmax(160px,1fr) minmax(220px,1.2fr) minmax(120px,0.8fr);gap:16px;align-items:end;margin-bottom:16px;">
                        <sw-text-field
                            v-model:value="searchTerm"
                            label="Suche"
                            placeholder="Nachricht suchen">
                        </sw-text-field>

                        <sw-single-select
                            v-model:value="selectedTopicGroup"
                            label="Bereich"
                            :options="topicGroupOptions">
                        </sw-single-select>

                        <sw-single-select
                            v-model:value="selectedTimeRange"
                            label="Zeitraum"
                            :options="timeRangeOptions">
                        </sw-single-select>

                        <sw-single-select
                            v-model:value="selectedLimit"
                            label="Pro Seite"
                            :options="limitOptions">
                        </sw-single-select>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                        <sw-button variant="primary" @click="applyFilters">Filter anwenden</sw-button>
                    </div>

                    <sw-data-grid
                        v-if="messages.length > 0"
                        :data-source="messages"
                        :columns="columns"
                        :show-selection="false">
                        <template #column-available_actions="{ item }">
                            {{ item.available_actions }}
                        </template>

                        <template #column-status="{ item }">
                            {{ item.status_label }}
                        </template>

                        <template #actions="{ item }">
                            <sw-context-menu-item
                                :router-link="{ name: 'mh.dashboard.detail', params: { id: item.id } }">
                                Details
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="item.status !== 'failed'"
                                @click="runAction('retry', item)">
                                Erneut senden
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!['failed', 'received', 'dispatched'].includes(item.status)"
                                @click="runAction('quarantine', item)">
                                Ausblenden
                            </sw-context-menu-item>
                            <sw-context-menu-item
                                :disabled="!['failed', 'received', 'dispatched', 'handled'].includes(item.status)"
                                @click="runAction('dismiss', item)">
                                Erledigen
                            </sw-context-menu-item>
                        </template>

                        <template #pagination>
                            <sw-pagination
                                :page="page"
                                :total="total"
                                :limit="selectedLimit"
                                :auto-hide="false"
                                @page-change="onPageChange">
                            </sw-pagination>
                        </template>
                    </sw-data-grid>

                    <div v-else style="margin-top:16px;">
                        Keine fehlgeschlagenen Nachrichten vorhanden.
                    </div>
                </sw-card>
            </template>
        </sw-page>
    `,data(){return{messages:[],isLoading:!1,total:0,page:1,searchTerm:"",selectedTopicGroup:"",selectedTimeRange:"30d",selectedLimit:25,topicGroupOptions:[{value:"",label:"Alle Bereiche"},{value:"Bestellungen",label:"Bestellungen"},{value:"Zahlungen",label:"Zahlungen"},{value:"System",label:"System"}],timeRangeOptions:[{value:"",label:"Gesamter Zeitraum"},{value:"1d",label:"Letzte 24 Stunden"},{value:"7d",label:"Letzte 7 Tage"},{value:"30d",label:"Letzte 30 Tage"},{value:"90d",label:"Letzte 90 Tage"}],limitOptions:[{value:10,label:"10"},{value:25,label:"25"},{value:50,label:"50"},{value:100,label:"100"}],columns:[{property:"created_at",label:"Erstellt",primary:!0},{property:"message_type",label:"Typ"},{property:"status",label:"Status"},{property:"retry_count",label:"Versuche"},{property:"available_actions",label:"Mögliche Aktion"}]}},created(){this.loadMessages()},methods:{async loadMessages(){this.isLoading=!0;try{const e=this.resolveTimeRange(),t=await this.mhDashboardApiService.listMessages({status:"failed",searchTerm:this.searchTerm.trim(),page:this.page,limit:this.selectedLimit,topicGroup:this.selectedTopicGroup,createdFrom:e.createdFrom,createdTo:e.createdTo});this.messages=t.data.data||[],this.total=t.data.total||0}catch{this.messages=[],this.total=0,this.createNotificationError({title:"Messenger Audit",message:"Die fehlgeschlagenen Nachrichten konnten nicht geladen werden."})}finally{this.isLoading=!1}},async runAction(e,t){try{e==="retry"&&await this.mhDashboardApiService.retryMessage(t.id),e==="quarantine"&&await this.mhDashboardApiService.quarantineMessage(t.id),e==="dismiss"&&await this.mhDashboardApiService.dismissMessage(t.id),await this.loadMessages()}catch{this.createNotificationError({title:"Messenger Audit",message:"Die Aktion konnte nicht ausgeführt werden."})}},onPageChange(e){this.page=e,this.loadMessages()},applyFilters(){this.page=1,this.loadMessages()},resolveTimeRange(){if(!this.selectedTimeRange)return{createdFrom:"",createdTo:""};const e=new Date,t=new Date(e);return this.selectedTimeRange==="1d"&&t.setDate(e.getDate()-1),this.selectedTimeRange==="7d"&&t.setDate(e.getDate()-7),this.selectedTimeRange==="30d"&&t.setDate(e.getDate()-30),this.selectedTimeRange==="90d"&&t.setDate(e.getDate()-90),{createdFrom:t.toISOString().slice(0,10),createdTo:e.toISOString().slice(0,10)}}}});const{Component:S}=Shopware;S.register("mh-dashboard-detail",{inject:["mhDashboardApiService"],mixins:[Shopware.Mixin.getByName("notification")],template:`
        <sw-page class="mh-dashboard-detail">
            <template #smart-bar-header>
                <h2>Nachrichtendetails</h2>
            </template>

            <template #smart-bar-actions>
                <sw-button @click="goBack">Zur Übersicht</sw-button>
                <sw-button variant="primary" @click="runAction('retry')" :disabled="!canRetry">Erneut senden</sw-button>
                <sw-button @click="runAction('quarantine')" :disabled="!canQuarantine">Ausblenden</sw-button>
                <sw-button @click="runAction('dismiss')" :disabled="!canDismiss">Erledigen</sw-button>
            </template>

            <template #content>
                <sw-card v-if="message" title="Nachricht">
                    <dl style="display:grid;grid-template-columns:220px 1fr;gap:12px 16px;">
                        <dt>ID</dt><dd>{{ message.id }}</dd>
                        <dt>Erstellt</dt><dd>{{ message.created_at }}</dd>
                        <dt>Aktualisiert</dt><dd>{{ message.updated_at }}</dd>
                        <dt>Bereich</dt><dd>{{ message.topic_group }}</dd>
                        <dt>Typ</dt><dd>{{ message.message_type }}</dd>
                        <dt>Name</dt><dd>{{ message.message_name }}</dd>
                        <dt>Bedeutung</dt><dd>{{ message.business_summary }}</dd>
                        <dt>Wirkung</dt><dd>{{ message.business_impact }}</dd>
                        <dt>Klasse</dt><dd>{{ message.message_class }}</dd>
                        <dt>Status</dt><dd>{{ message.status_label || message.status }}</dd>
                        <dt>Versuche</dt><dd>{{ message.retry_count }}</dd>
                    </dl>
                </sw-card>

                <sw-card v-if="message" title="Inhalt" style="margin-top:16px;">
                    <pre style="white-space:pre-wrap;word-break:break-word;">{{ message.payload_json }}</pre>
                </sw-card>

                <sw-card title="Verlauf" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="transitions.length > 0"
                        :data-source="transitions"
                        :columns="transitionColumns"
                        :show-selection="false"
                        :show-actions="false">
                    </sw-data-grid>
                    <div v-else>Kein Verlauf vorhanden.</div>
                </sw-card>

                <sw-card title="Fehler" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="failures.length > 0"
                        :data-source="failures"
                        :columns="failureColumns"
                        :show-selection="false"
                        :show-actions="false">
                    </sw-data-grid>
                    <div v-else>Keine Fehler vorhanden.</div>
                </sw-card>

                <sw-card title="Aktionen" style="margin-top:16px;">
                    <sw-data-grid
                        v-if="actions.length > 0"
                        :data-source="actions"
                        :columns="actionColumns"
                        :show-selection="false"
                        :show-actions="false">
                    </sw-data-grid>
                    <div v-else>Keine Aktionen vorhanden.</div>
                </sw-card>
            </template>
        </sw-page>
    `,data(){return{message:null,transitions:[],failures:[],actions:[],transitionColumns:[{property:"created_at",label:"Zeitpunkt"},{property:"event",label:"Ereignis"}],failureColumns:[{property:"created_at",label:"Zeitpunkt"},{property:"exception_class",label:"Fehlerklasse"},{property:"error_message",label:"Fehlermeldung"}],actionColumns:[{property:"created_at",label:"Zeitpunkt"},{property:"action",label:"Aktion"},{property:"reason",label:"Grund"}]}},computed:{messageId(){return this.$route.params.id},canRetry(){return this.message&&this.message.status==="failed"},canQuarantine(){return this.message&&["failed","received","dispatched"].includes(this.message.status)},canDismiss(){return this.message&&["failed","received","dispatched","handled"].includes(this.message.status)}},created(){this.loadDetail()},methods:{async loadDetail(){try{const e=await this.mhDashboardApiService.getMessageDetail(this.messageId);this.message=e.data.message||null,this.transitions=e.data.transitions||[],this.failures=e.data.failures||[],this.actions=e.data.actions||[]}catch{this.createNotificationError({title:"Messenger Audit",message:"Die Details konnten nicht geladen werden."})}},async runAction(e){try{e==="retry"&&await this.mhDashboardApiService.retryMessage(this.messageId),e==="quarantine"&&await this.mhDashboardApiService.quarantineMessage(this.messageId),e==="dismiss"&&await this.mhDashboardApiService.dismissMessage(this.messageId),await this.loadDetail()}catch{this.createNotificationError({title:"Messenger Audit",message:"Die Aktion konnte nicht ausgeführt werden."})}},goBack(){this.$router.push({name:"mh.dashboard.index"})}}});Shopware.Component.register("mh-dashboard-settings-icon",()=>w(()=>import("./index-M4f8anXa.js"),[]));Shopware.Module.register("mh-dashboard",{type:"plugin",name:"mh-dashboard",title:"Messenger Audit",description:"Admin module for messenger audit and failure handling",color:"#3d91ff",icon:"regular-history",favicon:"icon-module-settings.png",routes:{index:{component:"mh-dashboard-index",path:"index",meta:{parentPath:"sw.settings.index.plugins",privilege:"system.plugin_maintain"}},failed:{component:"mh-dashboard-failed",path:"failed",meta:{parentPath:"mh.dashboard.index",privilege:"system.plugin_maintain"}},detail:{component:"mh-dashboard-detail",path:"detail/:id",meta:{parentPath:"mh.dashboard.index",privilege:"system.plugin_maintain"}}},settingsItem:{group:"plugins",to:"mh.dashboard.index",iconComponent:"mh-dashboard-settings-icon",backgroundEnabled:!0,privilege:"system.plugin_maintain"}});
//# sourceMappingURL=messenger-history-dashboard-Dw71CKkE.js.map
