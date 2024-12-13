<script setup>
            import Alert from '@/Components/Alert.vue';
</script>
<template>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <Alert type="Info" hide-title>Use coupon CSQJET4R at checkout to get 25% off for Christmas & New Year!. Coupon valid till 10th Jan! Only for the first 20 new customers.</Alert>

            <div v-if="$slots.standard" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <slot name="standard"/>
            </div>
            
            <slot/>
        </div>
    </div>
</template>