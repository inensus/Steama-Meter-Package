<template>
    <div>
        <widget
                color="green"
                title="Settings"
        >
            <div class="md-layout md-gutter">

                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100 md-size-100">
                    <md-card class="setting-card">
                        <md-card-header>
                            Synchronization Settings
                        </md-card-header>
                        <md-card-content>
                            <div v-for="(setting,i) in settingService.list" :key="i">
                                <div v-if="setting.settingTypeName ==='sync_setting'" class="md-layout md-gutter">
                                    <div class="md-layout-item  md-xlarge-size-25 md-large-size-25 md-medium-size-25 md-small-size-25">
                                        <md-field>
                                            <label>{{setting.settingType.actionName}}</label>

                                        </md-field>
                                    </div>
                                    <div class="md-layout-item  md-xlarge-size-25 md-large-size-25 md-medium-size-25 md-small-size-25">
                                        <md-field :class="{'md-invalid': errors.has('each_'+setting.id)}">
                                            <label for="per">Each</label>
                                            <md-input
                                                    :id="'each_'+setting.id"
                                                    :name="'each_'+setting.id"
                                                    v-model="setting.settingType.syncInValueNum"
                                                    type="number"
                                                    v-validate="'required|min_value:1'"
                                            />
                                            <span class="md-error">{{ errors.first('each_'+setting.i) }}</span>
                                        </md-field>
                                    </div>
                                    <div class="md-layout-item  md-xlarge-size-25 md-large-size-25 md-medium-size-25 md-small-size-25">
                                        <md-field>
                                            <label for="period">{{ $tc('words.period') }}</label>
                                            <md-select name="period" v-model="setting.settingType.syncInValueStr"
                                                       id="period" v-validate="'required'">
                                                <md-option v-for="(p,i) in syncPeriods" :value="p" :key="i">{{p}}(s)
                                                </md-option>

                                            </md-select>
                                        </md-field>
                                    </div>
                                    <div class="md-layout-item  md-xlarge-size-25 md-large-size-25 md-medium-size-25 md-small-size-25">
                                        <md-field :class="{'md-invalid': errors.has('max_attempt_'+setting.id)}">
                                            <label for="max_attempt">Maximum Attempt(s)</label>
                                            <md-input
                                                    :id="'max_attempt_'+setting.id"
                                                    :name="'max_attempt_'+setting.id"
                                                    v-model="setting.settingType.maxAttempts"
                                                    type="number"
                                                    min="1"
                                                    v-validate="'required|min_value:1'"
                                            />
                                            <span class="md-error">{{ errors.first('max_attempt_'+setting.id) }}</span>
                                        </md-field>
                                    </div>
                                </div>
                            </div>
                        </md-card-content>
                        <md-card-actions>
                            <md-button class="md-raised md-primary" @click="updateSyncSetting()">Save</md-button>
                        </md-card-actions>
                        <md-progress-bar md-mode="indeterminate" v-if="loadingSync"/>

                    </md-card>
                </div>
                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100 md-size-100">
                    <md-card class="setting-card">
                        <md-card-header>
                            Sms Settings
                        </md-card-header>
                        <md-card-content>
                            <div v-for="(setting,i) in settingService.list" :key="i">
                                <div v-if="setting.settingTypeName ==='sms_setting'" class="md-layout md-gutter">
                                    <div class="md-layout-item  md-xlarge-size-33 md-large-size-33 md-medium-size-33 md-small-size-33">
                                        <md-field>
                                            <label>{{setting.settingType.state}}</label>

                                        </md-field>
                                    </div>
                                    <div class="md-layout-item  md-xlarge-size-33 md-large-size-33 md-medium-size-33 md-small-size-33">
                                        <md-field :class="{'md-invalid': errors.has('send_elder_'+setting.id)}">
                                            <label for="send_elder">Consider Only (created in last X minutes)</label>
                                            <md-input
                                                    :id="'send_elder_'+setting.id"
                                                    :name="'send_elder_'+setting.id"
                                                    v-model="setting.settingType.NotSendElderThanMins"
                                                    type="number"
                                                    min="10"
                                                    v-validate="'required|min_value:10'"
                                            />
                                            <span class="md-error">{{ errors.first('send_elder_'+setting.id) }}</span>
                                        </md-field>
                                    </div>

                                    <div class="md-layout-item  md-xlarge-size-33 md-large-size-33 md-medium-size-33 md-small-size-33">
                                        <md-checkbox v-model="setting.settingType.enabled" v-validate="'required'">
                                            Enabled
                                        </md-checkbox>
                                    </div>

                                </div>
                            </div>
                        </md-card-content>
                        <md-card-actions>
                            <md-button class="md-raised md-primary" @click="updateSmsSetting()">Save</md-button>
                        </md-card-actions>
                        <md-progress-bar md-mode="indeterminate" v-if="loadingSms"/>

                    </md-card>
                </div>
            </div>


        </widget>
    </div>
</template>

<script>


import { SettingService } from '../../services/SettingService'
import Widget from '../Shared/Widget'

export default {
    name: 'Setting',
    components: { Widget },
    data () {
        return {
            settingService: new SettingService(),
            loadingSync: false,
            loadingSms: false,
            syncPeriods: ['year','month','hour','week', 'day', 'minute']
        }
    },
    mounted () {
        this.getSettings()
    },
    methods: {
        async getSettings () {
            await this.settingService.getSettings()
        },
        async updateSyncSetting () {
            let validator = await this.$validator.validateAll()
            if (validator) {
                try {
                    this.loadingSync = true
                    await this.settingService.updateSyncSettings()
                    this.loadingSync = false
                    this.alertNotify('success', 'Sync settings updated.')
                } catch (e) {
                    this.loadingSync = false
                    this.alertNotify('error', e.message)
                }
            }
        },
        async updateSmsSetting () {

            let validator = await this.$validator.validateAll()
            if (validator) {
                try {

                    this.loadingSms = true
                    await this.settingService.updateSmsSettings()
                    this.loadingSms = false
                    this.alertNotify('success', 'Sms settings updated.')
                } catch (e) {
                    this.loadingSms = false
                    this.alertNotify('error', e.message)
                }
            }
        },
        alertNotify (type, message) {
            this.$notify({
                group: 'notify',
                type: type,
                title: type + ' !',
                text: message
            })
        },
    }
}
</script>

<style scoped>
    .setting-card {
        padding: 2rem !important;
    }
</style>