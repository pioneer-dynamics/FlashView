<script setup>
import { Link } from '@inertiajs/vue3';
import Logo from '../../images/logo.svg';
</script>

<template>
    <Link :href="'/'">
        <img :src="Logo" class="h-24 w-auto">
    </Link>
</template>
