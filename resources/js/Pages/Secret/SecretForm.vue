<script setup>
    import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
    import {encryption} from '../../encryption';
    import { computed, ref } from 'vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import SelectInput from '@/Components/SelectInput.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from '@/Components/Alert.vue';
    import { router } from '@inertiajs/vue3'
    import InputLabel from '@/Components/InputLabel.vue';

    const stage = ref('generating');


    const props = defineProps({
        secret: {
            type: String,
            default: null,
        },
        decryptUrl: {
            type: String,
            default: null,
        },
    })

    const passwordInput = ref(null);


    const decryptForm = useForm({})

    const other = useForm({
        password: null,
    });

    const decryptionSuccess = ref(false)

    const messageClass = computed(() => {
        if(props.secret) {
            if(!usePage().props.jetstream.flash?.secret?.message || decryptionSuccess.value === false) {
                return 'mt-1 block w-full blur-sm';
            }
            else {
                return 'mt-1 block w-full';
            }
        }
        else {
            return 'mt-1 block w-full';
        }
    })

    const getErrorMessage = (error) => {
        switch(error.code) {
            case 429: return "That's too many messages in a short time. Please wait try again in a minute.";
            default: return error.message;
        }
    }

    const numberWithCommas = (number) => {
        return number.toString().replace(/\B(?<!\.\d*)(?=(\d{3})+(?!\d))/g, ",");
    }


    const encryptData = () => {

        const e = new encryption();
        
        e.encryptMessage(form.message, other.password).then((data) => {
            form.transform((formdata) => ({
                ...formdata,
                message: data.secret
            }))
                .post(route('secret.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        if(usePage().props.jetstream.flash?.error)
                        {
                            form.setError('message', getErrorMessage(usePage().props.jetstream.flash.error));
                            
                            return;
                        }
                        if(data.passphrase != other.password)
                        {
                            other.password = data.passphrase;
                        }
                        
                        stage.value = 'generated';
                    },
                });
            
        }).
        catch((e) => {
            other.setError('password', e.message);
        });
    }

    const passwordPlaceholder = computed(() => {
        if(props.secret == null)
            return 'Enter a passsword, or leave blank to auto generate a password for you.';
        else
            return 'Enter your password to decrypt the message.'
    })

    const showPrivacyOptions = ref(false)

    const userType = computed(() => {
        let user_type = usePage().props?.auth?.user?.id ? 'user' : 'guest';

        if(usePage().props?.auth?.user?.id)
        {
            if(usePage().props?.auth?.user?.subscription)
            {
                user_type = 'subscribed';
            }
            else
            {
                user_type = 'user';
            }
        }
        else
        {
            user_type = 'guest';
        }

        return user_type
    })

    const expiryOptions = computed(() => {

        let max_expiry = 0;

        switch(userType.value) {
            case 'subscribed'
                : max_expiry =  usePage().props.auth.user.plan.settings.expiry.expiry_minutes;
                break;
            case 'user'
                : max_expiry =  usePage().props.config.secrets.expiry_limits.user;
                break;
            case 'guest'
                : max_expiry =  usePage().props.config.secrets.expiry_limits.guest;
                break;
        }

        return usePage().props.config.secrets.expiry_options.filter((option) => option.value <= max_expiry);
    })

    const form = useForm({
        message: props.secret ? 'This isn’t the actual message—it’s just a placeholder. To view the message, please click the button below.' : '',
        email: '',
        expires_in: expiryOptions.value[expiryOptions.value.length-1].value ,
    });

    const letsDoAnotherOne = () => {
        form.email = '';
        form.message = '';
        form.expires_in = expiryOptions.value[expiryOptions.value.length-1].value ;
        other.password = null;
        stage.value = 'generating';
        router.reload();
    }
    
    const maxLength = computed(() => {
        switch(userType.value) {
            case 'subscribed': return usePage().props.auth.user.plan.settings.messages.message_length;
            case 'user': return usePage().props.config.secrets.message_length.user;
            case 'guest': return usePage().props.config.secrets.message_length.guest;
        }
    });

    const decryptData = () => {
        const e = new encryption();
        decryptForm.get(props.decryptUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if(!usePage().props.jetstream.flash?.error && usePage().props.jetstream.flash?.error?.code != 404)
                {
                    const secretMessage = usePage().props.jetstream.flash.secret.message;
                    
                    const passphrase = other.password;

                    e.decryptMessage(secretMessage, passphrase)
                        .then((data) => {
                            form.message = data;
                            decryptionSuccess.value = true;
                        })
                        .catch((error) => {
                            form.setError('message', error);
                        });
                }
            },
            onError: () => {
                form.setError('message', 'Could not get your message. Either the password was wrong, or the message is already expired, or the message was already retrieved. You have no more attempts.');
            }
        })

        
    }
</script>
<style>
    .underline-svg {
      position: absolute;
      bottom: -12px; /* Adjust for spacing */
      left: 0;
      width: 100%;
      height: 20px; /* Adjust SVG height */
      fill: none;
      /* stroke: #3b82f6; Tailwind's blue-500 */
      @apply stroke-gamboge-500;
      stroke-width: 8; /* Double thickness */
      stroke-dasharray: 400; /* Total length of the path */
      stroke-dashoffset: 400; /* Initially hidden */
      animation: draw 1s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }

    @keyframes draw {
      to {
        stroke-dashoffset: 0;
      }
    }
</style>
<template>
    <FlatFormSection>
        <template #title>
            FlashView
        </template>

        <template #form>
            <div class="col-span-12">
                <Alert v-if="props.secret != null" type="Warning" hide-title>
                    <div class="mb-2">
                        You can attempt to retrieve this message 
                        <span class="relative inline-block">
                            ONLY ONCE
                            <svg class="underline-svg" xmlns="https://www.w3.org/2000/svg" viewBox="0 0 300 20">
                                <path d="M0 15 Q150 -10 300 15" />
                            </svg>
                        </span>.
                    </div>
                    <ol class="space-y-1 list-decimal list-inside">
                        <li>Wrong password will result in the message being deleted.</li>
                        <li>Correct password will show the message, and the message will be deleted.</li>
                        <li>Message will be deleted after the expiration time.</li>
                    </ol>
                </Alert>
                <Alert hide-title v-if="$page.props.jetstream.flash.error?.code == 404" type="Error">
                    This message has expired or has already been retrieved.
                </Alert>
            </div>
            <div class="col-span-12" v-if="stage=='generated'">
                <Alert hide-title type="Success">
                    Please share the link and password separately to the recipient. The message can be retrieved only once and only with both the link and the password. If you wish to prematurely delete the message, you may visit the link and enter any random password and click retrieve.
                </Alert>
            </div>
            <div class="col-span-12">
                <span v-if="stage=='generated'">
                    <InputLabel value="Retrieval Link"/>
                    <CodeBlock v-if="stage=='generated'" :value="$page.props.jetstream.flash?.secret?.url" class="break-words mt-1"/>
                </span>
                <span v-else>
                    <TextAreaInput :autofocus="props.secret == null" id="message" rows="7" v-model="form.message" type="text" :class="messageClass" placeholder="Your secret message..." :max-length="$page.props.jetstream.flash?.secret?.message ? 0 : maxLength"/>
                    <div class="flex flex-wrap mt-2 relative text-sm gap-2">
                        <div class="flex flex-wrap">
                            <svg xmlns="https://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-1">End-to-end encrypted</div>
                        </div>
                        <span v-if="!props.secret" class="flex flex-wrap gap-1">
                            <div v-if="!$page.props.auth.user || !$page.props.auth.user.subscription">|</div>
                            <div v-if="!$page.props.auth.user">
                                Is {{ maxLength }} characters too short, or need a longer expiry? - <Link class="underline text-gamboge-200" :href="route('login')">login</Link> or <Link class="underline text-gamboge-200" :href="route('register')">create a free account!</Link> to increase the limit.
                            </div>
                            <div v-else-if="!$page.props.auth.user.subscription" class="flex flex-wrap gap-1">
                                Is {{ maxLength }} characters still too short, or need a longer expiry? - <a as="a"class="underline text-gamboge-200" :href="route('plans.index')">subscribe to a paid plan</a> to increase the limits.
                            </div>
                        </span>
                    </div>
                    <div class="flex flex-wrap mt-2 gap-1">
                        <InputError :message="form.errors.message" />
                        <div v-if="form.errors.message?.length && $page.props.jetstream.flash.error?.code == 429" class="text-sm text-red-600 dark:text-red-400">
                            Or <Link class="underline text-gamboge-200" :href="route('login')">login</Link> or <Link class="underline text-gamboge-200" :href="route('register')">create a free account!</Link> to send more.
                        </div>
                    </div>
                </span>
            </div>
            <div class="col-span-12">
                <div class="flex flex-wrap sm:flex-nowrap gap-2 sm:space-y-0 sm:space-y-0">
                    <div class="w-full" v-if="$page.props.jetstream.flash?.secret?.message == undefined">
                        <span v-if="stage=='generated'">
                            <InputLabel value="Password"/>
                            <CodeBlock :value="other.password" class="mt-1"/>
                        </span>
                        <span v-else>
                            <TextInput id="password" :autofocus="props.secret != null" ref="passwordInput" v-model="other.password" type="text" class="mt-1 block w-full" :placeholder="passwordPlaceholder" />
                            <InputError :message="other.errors.password" class="mt-2" />
                        </span>
                    </div>
                    <div v-if="!$page.props.jetstream.flash?.secret?.url && props.secret == null">
                        <SelectInput id="expires_in" v-model="form.expires_in" class="mt-1 sm:w-full" :options="expiryOptions" />
                        <InputError :message="other.errors.expires_in" class="mt-2" />
                    </div>
                </div>
            </div>
            <div class="col-span-12" v-if="$page.props.auth.user">
                <span v-if="stage=='generated'">
                    <span v-if="form.email">
                        <InputLabel value="Recipient's Email"/>
                        <CodeBlock :value="form.email" class="mt-1"/>
                    </span>
                </span>
                <span v-else-if="!$page.props.jetstream.flash?.secret?.url && props.secret == null">
                    <TextInput v-model="form.email" placeholder="Recipient's email adddress (optional)" class="mt-1 block w-full" type="email"/>
                    <InputError :message="form.errors.email" class="mt-2" />
                </span>
            </div>
        </template>

        <template #actions>
            <span v-if="props.secret == null">
                <PrimaryButton @click.prevent="letsDoAnotherOne" v-if="$page.props.jetstream.flash?.secret?.url" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Let's do another one
                </PrimaryButton>
                <PrimaryButton @click.prevent="encryptData" v-else :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Generate link
                </PrimaryButton>
            </span>
            <span v-else>
                <PrimaryButton @click.prevent="decryptData" v-if="!$page.props.jetstream.flash?.secret?.message" :class="{ 'opacity-25': decryptForm.processing || (other.password?.length == 0 || other.password == null) }" :disabled="decryptForm.processing || (other.password?.length == 0 || other.password == null)">
                    Retrieve Message
                </PrimaryButton>
                <Link :href="route('welcome')" v-else class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150">
                    Send a new secret link
                </Link>
            </span>
        </template>
    </FlatFormSection>
</template>