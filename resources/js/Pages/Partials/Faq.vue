<script setup>
    import FlatActionSection from '@/Components/FlatActionSection.vue';
    import { Link } from '@inertiajs/vue3';
    import Faq from '@/Components/Faq.vue';
</script>
<template>
    <FlatActionSection v-if="!$page.props.jetstream.flash?.secret?.url" class="pt-4">
        <h2 class="text-4xl font-extrabold dark:text-white">F.A.Q.</h2>

        <Faq :question="'How safe is sharing a secret with ' + $page.props.config.app.name + ' ?'">
            The secret message is encrypted on your browser. The passphrase and the plaintext secret message is never sent to {{ $page.props.config.app.name }}. Only the encrypted message is sent to the server. So we have no means to decrypt the message. For the same reason, a hacker who gains complete access to all our systems will still not be able to decode the secret message.
        </Faq>
        <Faq question="How do you compare with other providers, like onetimesecret.com?">
            Most other providers like onetimesecret.com send the secret and password as plaintext to the server and the server does the encryption. Though the good people at onetimesecret.com do not misuse this, a man in the middle could still grab the secret message. {{ $page.props.config.app.name }} encrypts the message on your browser before sending it to the server. This way we, or a man in the middle, do not know what the secret is or what the passphrase to decrypt it is.
        </Faq>
        <Faq question="How secure is the encryption?">
            The encryption is based on AES-256-GCM which is one for most secure encryption algorithms available to mankind. With the existing technology it will take millions of years for someone to crack the encryption.
        </Faq>
        <Faq question="Why don't you add the passphrase to the link like other providers?">
            While it is convinient for the end user to encode the passphrase into the link, it would also mean that our server receives the passphrase and so does a man in the middle. By not including the passphrase in the link, we have completely cut off ourselves and any man in the middle from being able to retrieve the secret message.
        </Faq>
        <Faq question="How do I delete the secret message?">
            The secret message is deleted when it reaches the expiry set while generating the secret link, or on the first attempt to retrieve the message - whichever comes first. If you wish to manually delete the secret before it is retrieved, you could just visit the same link and give any random password and press retrieve to delete the message.
        </Faq>
        <Faq question="How long will the secret be available?">
            The secret message is deleted when it reaches the expiry set while generating the secret link, or on the first attempt to retrieve the message - whichever comes first.
        </Faq>
        <Faq question="What would be some of the usecases?">
            <ul class="list-disc list-inside">
                <li>For sending passwords, or share your Netflix credentials with familiy.</li>
                <li>To confess to your secret crush.</li>
                <li>Tell your kids about your grandfather's secret treasure stash.</li>
                <li>Literally anything...</li>
            </ul>
        </Faq>
        <Faq question="How do you avoid harrasment and other illegal use of the system?">
            We log the IP address and the time at which a link is generated and retrieved. This log is kept for {{$page.props.config.secrets.prune_after}} days after which they are permanently deleted as well. If a legal authority produces a court order to request details about a message, we will provide them with the IP address from which and the time at which the link was generated and accessed as long as it has not yet been pruned by the system.
        </Faq>
        <Faq question="Isn't that a privacy concern?">
            All secret messages are stored encrypted and encryption occurs at the browser before it is sent to us. So the content of the message is still secure and of no use to anyone except the recipient. We will not be able to retrieve the message content even if we wanted to. We will only share the metadata (IP address and time it was created, and the IP address and time it was retrieved) with legal authorities and only if they request it with a proper court order that relates to an investigation that is related to illegal use like harrasment or terrorism. In all other cases no metadata will be shared.
        </Faq>
        <Faq question="How are the metadata stored?">
            The IP Addresses is stored encrypted use AES-256-CBC algorithm with an "application key". This key is not stored in the database and is only stored on our application server. Hence a hack into our database will not reveal the metadata. The time at which a message was created or retrieved is not stored encrypted.
        </Faq>
        <Faq question="How can legal authorities contact you with a court order?">
            If you are a legal authority, you can email us at <a class="underline text-gamboge-200" :href="'mailto:' + $page.props.config.support.legal ">{{ $page.props.config.support.legal }}</a>. The request should have the retrieval URL for the message, a scanned copy of the notorised court order, and the reason for the request. To speed up the process (that is to help us validate the authority of the court order) we recommend that the email be sent from the registed domain name for the court or the legal authority and is signed using a valid digital signature.
        </Faq>
        <Faq question="Are there any limits to the number of secret links I can generate?">
            We rate limit the number of times an anonymous user can generate links to avoid abuse. Currently anonymous users can create {{ $page.props.config.secrets.rate_limit.guest.per_minute }} links per minute and {{ $page.props.config.secrets.rate_limit.guest.per_day }} links per day. <Link class="underline text-gamboge-200" :href="route('login')">Login</Link> or <Link class="underline text-gamboge-200" :href="route('register')">create a free account!</Link> to increase the limit. Authenticated users on the free plan can create {{ $page.props.config.secrets.rate_limit.user.per_minute }} links per minute and {{ $page.props.config.secrets.rate_limit.user.per_day }} links per day. We will soon introduce paid plans that will allow unlimited links to be created with support for larger messages.
        </Faq>
    </FlatActionSection>
</template>