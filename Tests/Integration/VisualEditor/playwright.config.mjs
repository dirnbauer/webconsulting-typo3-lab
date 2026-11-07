import {defineConfig} from '@playwright/test';
export default defineConfig({testDir:'.',testMatch:'runtime.spec.js',workers:1,retries:0,timeout:120000,expect:{timeout:20000},reporter:'list',use:{actionTimeout:20000,navigationTimeout:60000,ignoreHTTPSErrors:true,viewport:{width:1700,height:1000},trace:'retain-on-failure',screenshot:'only-on-failure'}});
