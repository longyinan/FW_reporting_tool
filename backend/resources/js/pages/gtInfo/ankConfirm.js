// survey-graph.js
import { ref} from 'vue';
import axios from 'axios';

// コンポーネントで使用する変数と関数をすべてエクスポート
export default function useSurveyGraph() {
    const questionList = ref([]);
    const respondentList = ref([]);
    const loading = ref(false);
    const isAllChecked = ref(true);
    const form = ref({
        sampleNos: '',    // 回答者ID
        sort: 1,          // ソース順 1=昇順 2=降順
        condition: '',    // 回答者条件式
    });

    const formCopy = ref({
        sampleNos: '',    // 回答者ID
        sort: 1,          // ソース順 1=昇順 2=降順
        condition: '',    // 回答者条件式
    });

    const page = ref({
        page: 1,
        per_page: 20,
        total: '',
    });
    // 2. 定数定義
    const appEl = document.getElementById('app');
    const id = appEl?.dataset.id || '';
    const url = appEl?.dataset.url || '';
    const requestInfoUrl = `${url}/ansGraph/${id}`;
    const requestShowAnkConfirm = `${url}/api/ansGraph/${id}/showAnkConfirm`;


    // 3. コア関数 - 基本データを取得
    const fetchData = async () => {
        try {
            loading.value = true;
            const response = await axios.get(requestInfoUrl);
            const data = response.data;
            questionList.value = getQuestionOptions(data.questionList);;
        } catch (error) {
            alert('データの読み込みに失敗しました');
        } finally {
            loading.value = false;
        }
    };
    const searchSubmit = async (targetPage = 1 ,type) => {
        try {
            loading.value = true;
            let postData
            if(type==1){
                postData = {
                    sampleNos: formCopy.value.sampleNos,
                    sort: formCopy.value.sort,
                    condition: formCopy.value.condition,
                    page:targetPage,
                    per_page:page.value.per_page,
                };
            }else{
                formCopy.value = JSON.parse(JSON.stringify(form.value));
                 postData = {
                    sampleNos: form.value.sampleNos,
                    sort: form.value.sort,
                    condition: form.value.condition,
                    page:targetPage,
                    per_page:page.value.per_page,
                };
            }

            const res = await axios.post(requestShowAnkConfirm, postData);
            respondentList.value = res.data.items
            page.value = res.data.pagination
        } catch (err) {
            if (err.response?.data?.errors?.condition) {
                respondentList.value = [];
                page.value.page = 1;
                page.value.per_page = 20;
                page.value.total = '';
                const msg = err.response.data.errors.condition[0];
                alert(msg);
            } else {
                alert('システムエラー')
            }
        } finally {
            loading.value = false;
        }
    };
    const getQuestionOptions = (questionList) => {
        const result = [];


        questionList.forEach(question => {
            const { type, subQuestions } = question;
            if (type === 'SA' || type === 'MA') {
                result.push(question);
            }
            else if (type !== 'FA' && type !== 'NU' && subQuestions && subQuestions.length) {
                subQuestions.forEach(sub => {
                    if (sub.type === 'SA' || sub.type === 'MA') {
                        result.push(sub);
                    }
                });
            }
        });

        return result;
    };
    const toggleAllCheck = () => {
        isAllChecked.value = !isAllChecked.value;
    };
    const getTotalPages = (pagination) => {
        if (!pagination || !pagination.total || !pagination.per_page) return 1;
        return Math.ceil(pagination.total / pagination.per_page);
    };
    return {
        fetchData,
        questionList,
        loading,
        toggleAllCheck,
        isAllChecked,
        form,
        searchSubmit,
        respondentList,
        page,
        getTotalPages
    };
}
