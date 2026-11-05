import {defineConfig} from '@playwright/test';
if (!process.env.VEE_AUTH_STATE) throw new Error('VEE_AUTH_STATE must point to a disposable backend session.');
export default defineConfig({testDir:'.',testMatch:'runtime.spec.js',workers:1,retries:0,timeout:120000,expect:{timeout:20000},reporter:'list',use:{actionTimeout:20000,navigationTimeout:60000,storageState:process.env.VEE_AUTH_STATE,ignoreHTTPSErrors:true,viewport:{width:1700,height:1000},trace:'retain-on-failure',screenshot:'only-on-failure'}});
