<template>
    <svg viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
        <style>
            .logo-primary { fill: #4F46E5; }
            .logo-bolt { fill: #1a1a2e; }
            @media (prefers-color-scheme: dark) {
                .logo-bolt { fill: #ffffff; }
            }
        </style>
        <defs>
            <clipPath id="shield-clip">
                <path d="M256 28L60 130v152c0 110.6 83.5 214 196 240 112.5-26 196-129.4 196-240V130L256 28z"/>
            </clipPath>
        </defs>
        <!-- Shield body -->
        <path class="logo-primary" d="M256 28L60 130v152c0 110.6 83.5 214 196 240 112.5-26 196-129.4 196-240V130L256 28z" opacity="0.12"/>
        <path class="logo-primary" d="M256 28L60 130v152c0 110.6 83.5 214 196 240 112.5-26 196-129.4 196-240V130L256 28zM256 444c-97.3-24.4-172-115.2-172-212V148.6L256 56.8l172 91.8V232c0 96.8-74.7 187.6-172 212z"/>
        <!-- Lightning bolt -->
        <path class="logo-bolt" d="M288 108L176 284h72l-32 120 128-192h-80l24-104z"/>
    </svg>
</template>
