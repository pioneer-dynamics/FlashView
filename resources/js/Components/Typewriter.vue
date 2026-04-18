<script setup>
import { nextTick, onMounted, ref } from 'vue';

    const props = defineProps({
        phrases: {
            type: Array,
            required: true
        },
        speed: {
            type: Number,
            default: 100
        },
    })

    const currentPhrase = ref(0);

    const currentScentence = ref('');

    const j = ref(0);

    const isDeleting = ref(false);

    onMounted(() => {
        setInterval(() => {
            if(isDeleting.value) {
                currentScentence.value = props.phrases[currentPhrase.value].substring(0, --j.value);
                if(j.value == 0) {
                    isDeleting.value = false;
                    currentPhrase.value = currentPhrase.value + 1;
                    if(currentPhrase.value == props.phrases.length) {
                        currentPhrase.value = 0;
                    }
                }
                
            }
            else {
                currentScentence.value = props.phrases[currentPhrase.value].substring(0, ++j.value);
                if(j.value == props.phrases[currentPhrase.value].length+5) {
                    isDeleting.value = true;
                }
            }
        }, props.speed)
    })
</script>
<template>
    <div class="w-full h-full flex justify-center items-center flex-wrap gap-2">
        <h1 class="text-4xl font-bold"><slot name="before"/><span class="text-gamboge-200 mr-2 border-r-2 animate-typing border-gamboge-200 pr-1">{{ currentScentence }}</span><slot name="after"/></h1>
    </div>
</template>