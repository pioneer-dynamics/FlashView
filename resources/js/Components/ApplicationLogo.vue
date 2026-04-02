<template>
    <svg viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
        <style>
            .logo-fill { fill: #fdbf80; }
            .logo-border { fill: #f49f1b; }
            .logo-bolt { fill: #3c2707; }
            @media (prefers-color-scheme: dark) {
                .logo-fill { fill: #5d3d0a; }
                .logo-border { fill: #f49f1b; }
                .logo-bolt { fill: #fedfc6; }
            }
        </style>
        <!-- Shield fill (solid background) -->
        <path class="logo-fill" d="M256 28L60 130v152c0 110.6 83.5 214 196 240 112.5-26 196-129.4 196-240V130L256 28z"/>
        <!-- Shield border -->
        <path class="logo-border" d="M256 28L60 130v152c0 110.6 83.5 214 196 240 112.5-26 196-129.4 196-240V130L256 28zM256 444c-97.3-24.4-172-115.2-172-212V148.6L256 56.8l172 91.8V232c0 96.8-74.7 187.6-172 212z"/>
        <!-- Lightning bolt -->
        <path class="logo-bolt" d="M288 108L176 284h72l-32 120 128-192h-80l24-104z"/>
    </svg>
</template>
