<template>
    <div id="container">
        <div id="contents">

            <!-- 固定ヘッダーエリア（WEB集計 + リサーチ情報をまとめて固定） -->
            <div class="fixed-header-wrapper">
                <!-- ヘッダー情報 -->
                <header class="top-fixed-bar" id="topFixedBar">
                    <div class="bar-content">
                        <div class="bar-left">
                            <h1 class="bar-title">WEB集計</h1>
                        </div>
                        <div class="bar-right">
                            <!-- 空欄 -->
                        </div>
                    </div>
                </header>

                <!-- リサーチ情報エリア（白色背景 + 固定） -->
                <div class="research-info-bar">
                    <div class="research-info-content">
                        <div class="research-info-left">
                            <div class="research-no">リサーチNO. {{ researchNo }}</div>
                            <div class="survey-title">{{ surveyTitle }}</div>
                        </div>
                        <div class="research-info-right">
                            <div class="total-reject-label">総回収打切数</div>
                            <div class="total-reject-value">{{ totalRejectCount }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 回収状況と打切設定 -->
            <div class="article" style="padding-top: 120px;">
                <h2 class="medium">
                    回収状況と打切設定
                    <span class="tooltip">
                        <span class="tooltiptext">合計の回収状況、及び打ち切り設定情報を確認することができます。</span>
                    </span>
                </h2>
                <table class="settingTable" v-for="(quota, quotaIdx) in quotaList" :key="quotaIdx">
                    <tbody>
                    <tr>
                        <th scope="col" class="number">No</th>
                        <th scope="col" class="condition">条件</th>
                        <th scope="col" class="quota">打切数</th>
                        <th scope="col" class="responses">回答数</th>
                        <th scope="col" class="incidence-rate">出現率(%)</th>
                    </tr>

                    <!-- データ行 -->
                    <tr
                        class="menu"
                        v-for="(cell, cellIdx) in quota.cell_infos"
                        :key="cellIdx"
                        :class="cellIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                    >
                        <td scope="row" class="number"><label>{{ cellIdx + 1 }}</label></td>
                        <td class="condition">{{ cell.quota_cell_name }}</td>
                        <!-- 打切数：空/0 表示 - -->
                        <td class="quota">
        <span class="uchi_num_st">
          {{ cell.num_target_samples || cell.num_target_samples === 0 ? cell.num_target_samples : '-' }}
        </span>
                        </td>
                        <!-- 回答数：元の値を保持（合計計算に使用） -->
                        <td class="responses">{{ cell.hon_count || 0 }}</td>
                        <!-- 出現率：空/0 表示 - -->
                        <td class="incidence-rate">
                            {{ cell.appearance_rate && cell.appearance_rate !== 0 ? cell.appearance_rate : '-' }}
                        </td>
                    </tr>

                    <!-- 合計行 -->
                    <tr class="summary">
                        <td colspan="2" scope="row" class="total">合計</td>
                        <td>-</td>
                        <td>
                            {{
                                quota.cell_infos.length > 0
                                    ? (quota.cell_infos.reduce((sum, item) => sum + (Number(item.hon_count) || 0), 0) || '-')
                                    : '-'
                            }}
                        </td>
                        <td>-</td>
                    </tr>
                    </tbody>
                </table>


                <div class="horizontal-spacer"></div>
                <div  v-if="displayStatus">
                    <table>
                        <tbody>
                        <tr>
                            <th style="width: 300px">設問</th>
                            <th>
                                <select
                                    style="width: 80%"
                                    class="select-default"
                                    v-model="selectedQCol"
                                >
                                    <option value="">--設問--</option>
                                    <option
                                        v-for="(item, optIdx) in getQuestionOptions(questionList)"
                                        :key="optIdx"
                                        :value="item.qCol"
                                    >
                                        {{ item.qNo }}({{item.type}}) {{ item.name }}
                                    </option>
                                </select>
                            </th>
                        </tr>
                        <tr>
                            <th>カテゴリー</th>
                            <th>
                                <select
                                    style="width: 80%"
                                    class="select-default"
                                    v-model="selectedCategory"
                                    :disabled="!selectedQCol"
                                >
                                    <option value="">--カテゴリ--</option>
                                    <option
                                        v-for="(cat, catIdx) in currentCategories"
                                        :key="catIdx"
                                        :value="cat.catNo"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </th>
                        </tr>
                        </tbody>

                    </table>
                    <div style="margin-top: 12px; display: flex; justify-content: space-between;">
                        <button
                            class="btn-clear cross-btn"
                            @click="resetFilter"
                        >リセット</button>
                        <button
                            class="btn-primary cross-btn"
                            @click="applyFilter"
                        >フィルタ</button>
                    </div>
                </div>
                <div class="horizontal-spacer"></div>
                <h2 class="medium" id="displayAnswerDataTitle">
                    <span style="text-transform: capitalize">設問</span>
                    <span class="tooltip">
                        <span class="tooltiptext">各設問においての回答内容を確認できます。</span>
                    </span>
                </h2>
                <div  v-if="displayStatus">

                    <div id="graphView">

                        <div
                            v-for="(question, idx) in questionList"
                            :key="idx"
                            class="questionWrapper"
                        >
                            <form
                                :name="question.qNo"
                                method="POST"
                                class="questionForm"
                            >
                                <!-- 設問タイトルテーブル -->
                                <table class="questionTable">
                                    <tbody>
                                    <tr>
                                        <th class="number">{{ idx+1 }}</th>
                                        <th class="condition question-header-cell">
                                            <div class="answer-type-wrapper">
                                                <span class="answer-type">{{ question.type }}</span>
                                            </div>
                                            <div class="cross-tab-wrapper" v-if="questionList && (question.type !='NU' && question.type !='FA' )">
                                                <select
                                                    name="crossTargetQuID"
                                                    class="select-default cross-select"
                                                    :key="question.qNo"
                                                    :ref="`crossSelect_${question.qNo}`"
                                                >
                                                    <option
                                                        v-for="(item, optIdx) in getCrossOptions(questionList, question)"
                                                        :key="item.q.qNo"
                                                        :value="item.q.qNo"
                                                    >
                                                        {{ item.originalIndex + 1 }} {{ item.q.qNo }} {{ item.q.name }}
                                                    </option>
                                                </select>
                                                <input
                                                    type="button"
                                                    class="btn-primary cross-btn"
                                                    value="クロス集計"
                                                    @click="showCross(question.qNo, $refs[`crossSelect_${question.qNo}`][0]?.value)"
                                                >
                                            </div>
                                        </th>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="question">
                                            <table class="question-title-table">
                                                <tbody>
                                                <tr>
                                                    <td valign="top" class="question-code-cell"><span class="color-primary">{{ question.qNo }}</span></td>
                                                    <td valign="top" class="question-text-cell" v-html="question.name"></td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>

                                <!-- 回答結果テーブル -->



                                <!-- 単一選択型（SA）回答 -->
                                <template v-if="question.type == 'SA' || question.type == 'MA'">
                                    <table class="answerTable">
                                        <tbody>
                                        <tr>
                                            <th class="number"></th>
                                            <th class="condition answer-condition-header">回答内容</th>
                                            <th class="result-rate">%</th>
                                            <th class="result-number">N</th>
                                            <th class="graph-header"></th>
                                        </tr>
                                        <tr
                                            v-for="(answer, ansIdx) in question.categories"
                                            :key="ansIdx"
                                            class="answer-row matrix-answer-detail"
                                            :class="ansIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                        >
                                            <td class="number">{{ ansIdx + 1 }}</td>
                                            <td class="condition answer-text-cell">{{ answer.name }}</td>
                                            <td class="result-rate">{{ answer.rate }}</td>
                                            <td class="result-number">{{ answer.count }}</td>
                                            <td class="graph-cell">
                                                <div
                                                    class="bar-color1 answer-bar"
                                                    :style="{ width: answer.rate > 0 ? `${answer.rate * 3}px` : '0px' }"
                                                ></div>
                                            </td>
                                        </tr>
                                        <!-- 合計行 -->
                                        <tr class="summary matrix-answer-detail_sum">
                                            <td colspan="3" align="right">合計 N:</td>
                                            <td align="right">{{ getTotalCount(question.categories) }}</td>
                                            <td class="graph-cell"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="answerTable">
                                        <tbody>
                                        <template    v-for="(answer, ansIdx) in question.categories"
                                                     :key="ansIdx" >
                                            <template v-if="answer.otherFa.length>0">

                                                <template    v-for="(other, otherIdx) in answer.otherFa"
                                                             :key="otherIdx" >
                                                    <tr class="matrix-answer">
                                                        <th class="fa-name">{{ question.qCol }}_snt{{answer.catNo}}_{{otherIdx+1}}</th>
                                                        <th class="fa-number">sample_no</th>
                                                        <th class="fa-content" colspan="3">{{ answer.name }}</th>
                                                    </tr>
                                                    <tr
                                                        v-for="(answeritem, itemIdx) in other.items"
                                                        :key="itemIdx"
                                                        class="matrix-answer-detail"
                                                        :class="itemIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                                    >
                                                        <td class="number">{{ itemIdx+1 }}</td>
                                                        <td class="number">{{ answeritem.sample_no }}</td>
                                                        <td class="condition answer-text-cell"  colspan="3">{{ answeritem.value }}</td>
                                                    </tr>
                                                    <tr v-if="answer.otherFa.length > 0 && answer.otherFa[otherIdx].pagination">
                                                        <td colspan="5" class="fa-pagination-cell" style="text-align: center">
                                                            <div class="pagination-wrapper">
                                                                <!-- 最初のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn prev-page-btn"
                                                                    :disabled="answer.otherFa[otherIdx].pagination.page <= 1"
                                                                    @click.prevent="changePage(answer, 1,otherIdx,'other',question.qCol)"
                                                                >
                                                                    トップ
                                                                </button>

                                                                <!-- 前のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn prev-page-btn"
                                                                    :disabled="answer.otherFa[otherIdx].pagination.page <= 1"
                                                                    @click.prevent="changePage(answer, answer.otherFa[otherIdx].pagination.page - 1,otherIdx,'other',question.qCol)"
                                                                >
                                                                    前へ
                                                                </button>

                                                                <!-- ページ番号エリア -->
                                                                <div class="pagination-numbers">
                                                                    <!-- 現在のページを表示 -->
                                                                    <span class="page-number active">{{ answer.otherFa[otherIdx].pagination.page }}</span>

                                                                    <!-- 総ページ数を計算 -->
                                                                    <template v-if="getTotalPages(answer.otherFa[otherIdx].pagination) > 1">
                                                                        <!-- 次のページを表示（存在する場合） -->
                                                                        <span
                                                                            v-if="answer.otherFa[otherIdx].pagination.page + 1 <= getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                            class="page-number"
                                                                            @click="changePage(answer, answer.otherFa[otherIdx].pagination.page + 1,otherIdx,'other',question.qCol)"
                                                                        >
                            {{ answer.otherFa[otherIdx].pagination.page + 1 }}
                        </span>

                                                                        <!-- 省略記号（総ページ数が現在のページ+2より大きい場合に表示） -->
                                                                        <span
                                                                            v-if="answer.otherFa[otherIdx].pagination.page + 2 < getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                            class="page-ellipsis"
                                                                        >
                            ...
                        </span>

                                                                        <!-- 最後のページ -->
                                                                        <span
                                                                            v-if="answer.otherFa[otherIdx].pagination.page < getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                            class="page-number"
                                                                            @click="changePage(answer, getTotalPages(answer.otherFa[otherIdx].pagination),otherIdx,'other',question.qCol)"
                                                                        >
                            {{ getTotalPages(answer.otherFa[otherIdx].pagination) }}
                        </span>
                                                                    </template>
                                                                </div>

                                                                <!-- 次のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn next-page-btn"
                                                                    :disabled="answer.otherFa[otherIdx].pagination.page >= getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                    @click.prevent="changePage(answer, answer.otherFa[otherIdx].pagination.page + 1,otherIdx,'other',question.qCol)"
                                                                >
                                                                    次へ
                                                                </button>

                                                                <!-- 最後のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn next-page-btn"
                                                                    :disabled="answer.otherFa[otherIdx].pagination.page >= getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                    @click.prevent="changePage(answer, getTotalPages(answer.otherFa[otherIdx].pagination),otherIdx,'other',question.qCol)"
                                                                >
                                                                    最後
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>

                                            </template>

                                        </template>
                                        </tbody>
                                    </table>
                                </template>

                                <template v-if="question.type == 'FA' || question.type == 'NU'">
                                    <table class="answerTable">
                                        <tbody>
                                        <template
                                            v-for="(answer, ansIdx) in question.categories"
                                            :key="ansIdx"
                                        >
                                            <tr>
                                                <th class="fa-name">{{ question.qCol }}_{{answer.catNo}}</th>
                                                <th class="fa-number">sample_no</th>
                                                <th class="fa-content">回答内容</th>
                                            </tr>

                                            <tr
                                                v-for="(answeritem, itemIdx) in answer.items"
                                                :key="itemIdx"
                                                class="answer-row matrix-answer-detail"
                                                :class="itemIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                            >
                                                <td class="number">{{ itemIdx+1 }}</td>
                                                <td class="number">{{ answeritem.sample_no }}</td>
                                                <td class="condition answer-text-cell">{{ answeritem.value }}</td>
                                            </tr>

                                            <!-- 動的ページネーションエリア -->
                                            <tr v-if="question.categories.length > 0 && question.categories[ansIdx].pagination">
                                                <td colspan="3" class="fa-pagination-cell" style="text-align: center">
                                                    <div class="pagination-wrapper">
                                                        <!-- 最初のページボタン（無効化制御） -->
                                                        <button
                                                            class="pagination-btn prev-page-btn"
                                                            :disabled="question.categories[ansIdx].pagination.page <= 1"
                                                            @click.prevent="changePage(question, 1,ansIdx)"
                                                        >
                                                            トップ
                                                        </button>

                                                        <!-- 前のページボタン（無効化制御） -->
                                                        <button
                                                            class="pagination-btn prev-page-btn"
                                                            :disabled="question.categories[ansIdx].pagination.page <= 1"
                                                            @click.prevent="changePage(question, question.categories[ansIdx].pagination.page - 1,ansIdx)"
                                                        >
                                                            前へ
                                                        </button>

                                                        <!-- ページ番号エリア -->
                                                        <div class="pagination-numbers">
                                                            <!-- 現在のページを表示 -->
                                                            <span class="page-number active">{{ question.categories[ansIdx].pagination.page }}</span>

                                                            <!-- 総ページ数を計算 -->
                                                            <template v-if="getTotalPages(question.categories[ansIdx].pagination) > 1">
                                                                <!-- 次のページを表示（存在する場合） -->
                                                                <span
                                                                    v-if="question.categories[ansIdx].pagination.page + 1 <= getTotalPages(question.categories[ansIdx].pagination)"
                                                                    class="page-number"
                                                                    @click="changePage(question, question.categories[ansIdx].pagination.page + 1,ansIdx)"
                                                                >
                            {{ question.categories[ansIdx].pagination.page + 1 }}
                        </span>

                                                                <!-- 省略記号（総ページ数が現在のページ+2より大きい場合に表示） -->
                                                                <span
                                                                    v-if="question.categories[ansIdx].pagination.page + 2 < getTotalPages(question.categories[ansIdx].pagination)"
                                                                    class="page-ellipsis"
                                                                >
                            ...
                        </span>

                                                                <!-- 最後のページ -->
                                                                <span
                                                                    v-if="question.categories[ansIdx].pagination.page < getTotalPages(question.categories[ansIdx].pagination)"
                                                                    class="page-number"
                                                                    @click="changePage(question, getTotalPages(question.categories[ansIdx].pagination),ansIdx)"
                                                                >
                            {{ getTotalPages(question.categories[ansIdx].pagination) }}
                        </span>
                                                            </template>
                                                        </div>

                                                        <!-- 次のページボタン（無効化制御） -->
                                                        <button
                                                            class="pagination-btn next-page-btn"
                                                            :disabled="question.categories[ansIdx].pagination.page >= getTotalPages(question.categories[ansIdx].pagination)"
                                                            @click.prevent="changePage(question, question.categories[ansIdx].pagination.page + 1,ansIdx)"
                                                        >
                                                            次へ
                                                        </button>

                                                        <!-- 最後のページボタン（無効化制御） -->
                                                        <button
                                                            class="pagination-btn next-page-btn"
                                                            :disabled="question.categories[ansIdx].pagination.page >= getTotalPages(question.categories[ansIdx].pagination)"
                                                            @click.prevent="changePage(question, getTotalPages(question.categories[ansIdx].pagination),ansIdx)"
                                                        >
                                                            最後
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        </tbody>
                                    </table>

                                </template>

                                <!-- マトリクスSA型回答 -->
                                <template v-if="question.type != 'SA' && question.type != 'MA' && question.type != 'FA' && question.type != 'NU'">
                                    <template v-for="(subQuestion, subIdx) in question.subQuestions" :key="subIdx">
                                        <template v-if="subQuestion.type=='SA'">
                                            <table class="answerTable">
                                                <tbody>
                                                <!-- マトリクス項目行 -->
                                                <tr class="matrix-answer">
                                                    <th class="number">{{ subQuestion.qCol }}</th>
                                                    <th class="condition matrix-item-label">{{ subQuestion.name }}</th>
                                                    <th class="result-rate">{{ subQuestion.qCol }} N:</th>
                                                    <th class="result-number">{{ getTotalCount(subQuestion.categories) }}</th>
                                                    <th class="graph-cell">
                                                        <div class="matrix-bar-group">
                                                            <div
                                                                v-for="(bar, barIdx) in subQuestion.categories"
                                                                :key="barIdx"

                                                                :class="`fl bar-color${barIdx + 1} matrix-bar`"
                                                                :style="{ width: `${Number(bar?.rate) || 0}rem`,    display: (bar && Number(bar?.rate) > 0) ? 'block' : 'none' } "
                                                            ></div>
                                                        </div>
                                                    </th>
                                                </tr>

                                                <!-- マトリクス詳細回答 -->
                                                <tr
                                                    v-for="(answer, ansIdx) in subQuestion.categories"
                                                    :key="ansIdx"
                                                    class="matrix-answer-detail"
                                                    :class="ansIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                                >
                                                    <td class="number"></td>
                                                    <td class="condition matrix-detail-cell">
                                                        <div :class="`bar-color${ansIdx+1} fl matrix-detail-color`"></div>
                                                        <span class="matrix-detail-label">{{ answer.name }}</span>
                                                    </td>
                                                    <td class="result-rate">{{ answer.rate || 0 }}</td>
                                                    <td class="result-number">{{ answer.count || 0 }}</td>
                                                    <td class="graph-cell"></td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </template>

                                        <template v-if="subQuestion.type=='MA'">
                                            <table class="answerTable">
                                                <tbody>
                                                <!-- マトリクス項目行 -->
                                                <tr class="matrix-answer">
                                                    <th class="number">{{ subQuestion.qCol }}</th>
                                                    <th class="condition matrix-item-label">{{ subQuestion.name }}</th>
                                                    <th class="result-rate">{{ subQuestion.qCol }} N:</th>
                                                    <th class="result-number">{{ getTotalCount(subQuestion.categories) }}</th>
                                                    <th class="graph-cell">
                                                    </th>
                                                </tr>

                                                <!-- マトリクス詳細回答 -->
                                                <tr
                                                    v-for="(answer, ansIdx) in subQuestion.categories"
                                                    :key="ansIdx"
                                                    class="matrix-answer-detail"
                                                    :class="ansIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                                >
                                                    <td class="number"></td>
                                                    <td class="condition matrix-detail-cell">
                                                        <div :class="`bar-color${ansIdx+1} fl matrix-detail-color`"></div>
                                                        <span class="matrix-detail-label">{{ answer.name }}</span>
                                                    </td>
                                                    <td class="result-rate">{{ answer.rate || 0 }}</td>
                                                    <td class="result-number">{{ answer.count || 0 }}</td>
                                                    <td class="graph-cell">
                                                        <div
                                                            class="bar-color1 answer-bar"
                                                            :style="{ width: answer.rate > 0 ? `${answer.rate * 3}px` : '0px' }"
                                                        ></div>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </template>

                                        <template v-if="subQuestion.type=='MA' || subQuestion.type=='SA'">
                                            <table class="answerTable">
                                                <tbody>
                                                <template    v-for="(answer, ansIdx) in subQuestion.categories"
                                                             :key="ansIdx" >
                                                    <template v-if="answer.otherFa.length>0">

                                                        <template    v-for="(other, otherIdx) in answer.otherFa"
                                                                     :key="otherIdx" >
                                                            <tr class="matrix-answer">
                                                                <th class="fa-name">{{ subQuestion.qCol }}_snt{{answer.catNo}}_{{otherIdx+1}}</th>
                                                                <th class="fa-number">sample_no</th>
                                                                <th class="fa-content" colspan="3">{{ answer.name }}</th>
                                                            </tr>
                                                            <tr
                                                                v-for="(answeritem, itemIdx) in other.items"
                                                                :key="itemIdx"
                                                                class="matrix-answer-detail"
                                                                :class="itemIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                                            >
                                                                <td class="number">{{ itemIdx+1 }}</td>
                                                                <td class="number">{{ answeritem.sample_no }}</td>
                                                                <td class="condition answer-text-cell"  colspan="3">{{ answeritem.value }}</td>
                                                            </tr>
                                                            <tr v-if="answer.otherFa.length > 0 && answer.otherFa[otherIdx].pagination">
                                                                <td colspan="5" class="fa-pagination-cell" style="text-align: center">
                                                                    <div class="pagination-wrapper">
                                                                        <!-- 最初のページボタン（無効化制御） -->
                                                                        <button
                                                                            class="pagination-btn prev-page-btn"
                                                                            :disabled="answer.otherFa[otherIdx].pagination.page <= 1"
                                                                            @click.prevent="changePage(answer, 1,otherIdx,'other',subQuestion.qCol)"
                                                                        >
                                                                            トップ
                                                                        </button>

                                                                        <!-- 前のページボタン（無効化制御） -->
                                                                        <button
                                                                            class="pagination-btn prev-page-btn"
                                                                            :disabled="answer.otherFa[otherIdx].pagination.page <= 1"
                                                                            @click.prevent="changePage(answer, answer.otherFa[otherIdx].pagination.page - 1,otherIdx,'other',subQuestion.qCol)"
                                                                        >
                                                                            前へ
                                                                        </button>

                                                                        <!-- ページ番号エリア -->
                                                                        <div class="pagination-numbers">
                                                                            <!-- 現在のページを表示 -->
                                                                            <span class="page-number active">{{ answer.otherFa[otherIdx].pagination.page }}</span>

                                                                            <!-- 総ページ数を計算 -->
                                                                            <template v-if="getTotalPages(answer.otherFa[otherIdx].pagination) > 1">
                                                                                <!-- 次のページを表示（存在する場合） -->
                                                                                <span
                                                                                    v-if="answer.otherFa[otherIdx].pagination.page + 1 <= getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                                    class="page-number"
                                                                                    @click="changePage(answer, answer.otherFa[otherIdx].pagination.page + 1,otherIdx,'other',subQuestion.qCol)"
                                                                                >
                            {{ answer.otherFa[otherIdx].pagination.page + 1 }}
                        </span>

                                                                                <!-- 省略記号（総ページ数が現在のページ+2より大きい場合に表示） -->
                                                                                <span
                                                                                    v-if="answer.otherFa[otherIdx].pagination.page + 2 < getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                                    class="page-ellipsis"
                                                                                >
                            ...
                        </span>

                                                                                <!-- 最後のページ -->
                                                                                <span
                                                                                    v-if="answer.otherFa[otherIdx].pagination.page < getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                                    class="page-number"
                                                                                    @click="changePage(answer, getTotalPages(answer.otherFa[otherIdx].pagination),otherIdx,'other',subQuestion.qCol)"
                                                                                >
                            {{ getTotalPages(answer.otherFa[otherIdx].pagination) }}
                        </span>
                                                                            </template>
                                                                        </div>

                                                                        <!-- 次のページボタン（無効化制御） -->
                                                                        <button
                                                                            class="pagination-btn next-page-btn"
                                                                            :disabled="answer.otherFa[otherIdx].pagination.page >= getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                            @click.prevent="changePage(answer, answer.otherFa[otherIdx].pagination.page + 1,otherIdx,'other',subQuestion.qCol)"
                                                                        >
                                                                            次へ
                                                                        </button>

                                                                        <!-- 最後のページボタン（無効化制御） -->
                                                                        <button
                                                                            class="pagination-btn next-page-btn"
                                                                            :disabled="answer.otherFa[otherIdx].pagination.page >= getTotalPages(answer.otherFa[otherIdx].pagination)"
                                                                            @click.prevent="changePage(answer, getTotalPages(answer.otherFa[otherIdx].pagination),otherIdx,'other',subQuestion.qCol)"
                                                                        >
                                                                            最後
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </template>

                                                    </template>

                                                </template>
                                                </tbody>
                                            </table>

                                        </template>
                                        <template v-if="subQuestion.type == 'FA' || subQuestion.type == 'NU'">
                                            <table class="answerTable">
                                                <tbody>
                                                <template
                                                    v-for="(answer, ansIdx) in subQuestion.categories"
                                                    :key="ansIdx"
                                                >
                                                    <tr class="matrix-answer">
                                                        <th class="fa-name">{{ subQuestion.qCol }}_{{answer.catNo}}</th>
                                                        <th class="fa-number">sample_no</th>
                                                        <th class="fa-content" colspan="3">{{ subQuestion.name }}</th>
                                                    </tr>

                                                    <tr
                                                        v-for="(answeritem, itemIdx) in answer.items"
                                                        :key="itemIdx"
                                                        class="matrix-answer-detail"
                                                        :class="itemIdx % 2 === 0 ? 'answer-row-even' : 'answer-row-odd'"
                                                    >
                                                        <td class="number">{{ itemIdx+1 }}</td>
                                                        <td class="number">{{ answeritem.sample_no }}</td>
                                                        <td class="condition answer-text-cell"  colspan="3">{{ answeritem.value }}</td>
                                                    </tr>

                                                    <!-- 動的ページネーションエリア -->
                                                    <tr v-if="subQuestion.categories.length > 0 && subQuestion.categories[ansIdx].pagination">
                                                        <td colspan="5" class="fa-pagination-cell" style="text-align: center">
                                                            <div class="pagination-wrapper">
                                                                <!-- 最初のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn prev-page-btn"
                                                                    :disabled="subQuestion.categories[ansIdx].pagination.page <= 1"
                                                                    @click.prevent="changePage(subQuestion, 1,ansIdx)"
                                                                >
                                                                    トップ
                                                                </button>

                                                                <!-- 前のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn prev-page-btn"
                                                                    :disabled="subQuestion.categories[ansIdx].pagination.page <= 1"
                                                                    @click.prevent="changePage(subQuestion, subQuestion.categories[ansIdx].pagination.page - 1,ansIdx)"
                                                                >
                                                                    前へ
                                                                </button>

                                                                <!-- ページ番号エリア -->
                                                                <div class="pagination-numbers">
                                                                    <!-- 現在のページを表示 -->
                                                                    <span class="page-number active">{{ subQuestion.categories[ansIdx].pagination.page }}</span>

                                                                    <!-- 総ページ数を計算 -->
                                                                    <template v-if="getTotalPages(subQuestion.categories[ansIdx].pagination) > 1">
                                                                        <!-- 次のページを表示（存在する場合） -->
                                                                        <span
                                                                            v-if="subQuestion.categories[ansIdx].pagination.page + 1 <= getTotalPages(subQuestion.categories[ansIdx].pagination)"
                                                                            class="page-number"
                                                                            @click="changePage(subQuestion, subQuestion.categories[ansIdx].pagination.page + 1,ansIdx)"
                                                                        >
                            {{ subQuestion.categories[ansIdx].pagination.page + 1 }}
                        </span>

                                                                        <!-- 省略記号（総ページ数が現在のページ+2より大きい場合に表示） -->
                                                                        <span
                                                                            v-if="subQuestion.categories[ansIdx].pagination.page + 2 < getTotalPages(subQuestion.categories[ansIdx].pagination)"
                                                                            class="page-ellipsis"
                                                                        >
                            ...
                        </span>

                                                                        <!-- 最後のページ -->
                                                                        <span
                                                                            v-if="subQuestion.categories[ansIdx].pagination.page < getTotalPages(subQuestion.categories[ansIdx].pagination)"
                                                                            class="page-number"
                                                                            @click="changePage(subQuestion, getTotalPages(subQuestion.categories[ansIdx].pagination),ansIdx)"
                                                                        >
                            {{ getTotalPages(subQuestion.categories[ansIdx].pagination) }}
                        </span>
                                                                    </template>
                                                                </div>

                                                                <!-- 次のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn next-page-btn"
                                                                    :disabled="subQuestion.categories[ansIdx].pagination.page >= getTotalPages(subQuestion.categories[ansIdx].pagination)"
                                                                    @click.prevent="changePage(subQuestion, subQuestion.categories[ansIdx].pagination.page + 1,ansIdx)"
                                                                >
                                                                    次へ
                                                                </button>

                                                                <!-- 最後のページボタン（無効化制御） -->
                                                                <button
                                                                    class="pagination-btn next-page-btn"
                                                                    :disabled="subQuestion.categories[ansIdx].pagination.page >= getTotalPages(subQuestion.categories[ansIdx].pagination)"
                                                                    @click.prevent="changePage(subQuestion, getTotalPages(subQuestion.categories[ansIdx].pagination),ansIdx)"
                                                                >
                                                                    最後
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                                </tbody>
                                            </table>

                                        </template>
                                    </template>
                                </template>
                            </form>
                        </div>

                    </div>
                </div>
                <div v-else>
                    <div style="text-align: left;">
                        <a
                            id="displayAnserData"
                            href="#"
                            @click.prevent="displayAnswerData()"
                            class="btn-primary btn-large"
                        >
                            回答データ表示
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <GlobalLoading :visible="loading" />
    </div>
    <!-- ポップアップコード -->
    <!-- ポップアップマスクレイヤー -->
    <div class="modal-mask" v-if="showModal"></div>
    <!-- ポップアップ本体 -->
    <div class="modal-container" v-if="showModal">
        <!-- 閉じるボタン -->
        <button class="modal-close-btn" @click="closeModal()">×</button>
        <!-- ポップアップ内容 -->
        <div class="modal-content"
             v-for="(cross, crossIdx) in crossInfo"
        >
            <table class="result-table">
                <colgroup>
                    <col style="width: 140px;">
                    <col style="width: 100px;">
                    <col style="width: 60px;">
                    <col v-for="item in cross.headCatNames"style="width: 60px;">
                </colgroup>
                <thead>
                <tr>
                    <th class="no-border"></th>
                    <th class="no-border"></th>
                    <th class="no-border"></th>
                    <th :colspan="cross.headCatNames?.length ">{{cross.headName}}</th>

                </tr>

                </thead>
                <thead>
                <tr>
                    <th class="no-border"></th>
                    <th class="no-border"></th>
                    <th >N</th>
                    <th
                        v-for="(catName, catIds) in cross.headCatNames"
                    >{{ catName.name }}</th>

                </tr>

                </thead>
                <tbody>
                <tr>
                    <td :rowspan="cross.rows?.length*3" style="width: 300px;">{{cross.sideName}}</td>

                </tr>
                <template
                    v-for="(row, rowIds) in cross.rows"
                >
                    <tr
                        class="answer-row-even"

                    >
                        <td rowspan="2">{{ row.name }}</td>
                        <td>{{ row.cells.reduce((sum, item) => sum + (item.count || 0), 0) }}</td>
                        <td
                            v-for="(cell, cellIds) in row.cells"
                        >{{ cell.count }}</td>

                    </tr>
                    <tr
                        class="answer-row-odd"
                    >
                        <td>{{ Math.round(row.cells.reduce((sum, item) => sum + (item.rate || 0), 0)) }}%</td>
                        <td
                            v-for="(cell, cellIds) in row.cells"
                        >{{ cell.rate }}%</td>

                    </tr>

                </template>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import useSurveyGraph from './gtInfo.js';
import { onMounted, onUnmounted } from 'vue';
import GlobalLoading from './common.vue'
// 必要な変数とメソッドを展開
const {
    displayStatus,
    quotaList,
    questionList,
    researchNo,
    surveyTitle,
    loading,
    totalRejectCount,
    currentCategories,  // ← 追加
    selectedQCol,       // ← 追加
    selectedCategory,   // ← 追加
    getCrossOptions,
    displayAnswerData,
    changePage,
    getTotalCount,
    getTotalPages,
    showCross,
    init,
    destroy,
    openModal,
    closeModal,
    showModal,
    crossInfo,
    getQuestionOptions,
    resetFilter,
    applyFilter
} = useSurveyGraph();

// マウント時に初期化
onMounted(() => {
    init();
});

// アンマウント時に破棄処理
onUnmounted(() => {
    destroy();
});
</script>

<style scoped>
@import '../../../css/index.css';
</style>
