<template>
  <!-- verifica si la carta de recomendacion en pdf corresponde a los datos de la tabla
          Si corresponde entonces se ha realizado 
          Si no entonces esta pendiente
         -->
  <div class="container-fluid mt-2">
    <div class="row mx-3 ">
      <strong>Nota: </strong>
      <p>
         &nbsp;&nbsp;Ingresa a continuación dos correos válidos de quienes te otorguen las cartas de recomendación.<br />
      </p>
    </div>

    <!-- CASO 1 -->
    <!-- No existen cartas de recomendacion se crearan por primera vez -->
    <div class="row" v-if="sizeRecommendationLetter() == 0">
      <!-- Recorre la lista de correos de ejemplo, el usuario debera confirmar al aceptar -->
        <div
          class="form-group col-6 d-flex"
          v-for="(my_email, index) in emails"
          :key="index"
        >
          <!-- No existe carta de recomendacion pero se creara -->
          <valida-carta-recomendacion
            :email="my_email.email"
            :archive_id="archive_id"
            :appliant="appliant"
            :academic_program="academic_program"
            :index="index + 1"
            :images_btn="images_btn"
          >
          </valida-carta-recomendacion>
        </div>
     
    </div>

    <div class="row" v-else-if="sizeRecommendationLetter() == 1">
        <div class="form-group col-6 d-flex">
          <valida-carta-recomendacion
            :email="recommendation_letters[0].email_evaluator"
            :recommendation_letter="recommendation_letters[0]"
            :archive_recommendation_letter="archives_recommendation_letters[0]"
            :appliant="appliant"
            :archive_id="archive_id"
            :academic_program="academic_program"
                        :images_btn="images_btn"

            :index = 1
            
          >
          </valida-carta-recomendacion>
        </div>

        <div class="form-group col-6 d-flex">
          
          <valida-carta-recomendacion
            :email="emails[0].email"
            :appliant="appliant"
            :archive_id="archive_id"
            :academic_program="academic_program"
            :images_btn="images_btn"
            :index = 2
          >
          </valida-carta-recomendacion>
        </div>
    </div>

    <!-- CASO 3 -->
    <!-- Ya existen dos correos registrados para carta de recomendacion  -->
    <div class="row " v-else>
      
        <div
          class="form-group col-6 d-flex"
          v-for="(rl, index) in recommendation_letters"
          :key="index"
        >
          <!-- Se comprueba el estado de las dos cartas / Se pueden modificar campos aun -->
          <valida-carta-recomendacion
            :email="rl.email_evaluator"
            :recommendation_letter="recommendation_letters[index]"
            :archive_recommendation_letter="archives_recommendation_letters[index]"
            :appliant="appliant"
            :archive_id="archive_id"
            :academic_program="academic_program"
            :images_btn="images_btn"
            :index="index + 1"
          >
          </valida-carta-recomendacion>
        </div>
      </div>
    </div>

</template>

<script>
import ValidaCartaRecomendacion from "./ValidaCartaRecomendacion.vue";

export default {
  name: "carta-recomendacion",

  components: {
    ValidaCartaRecomendacion,
  },

  data() {
    return {
      emails: [
        { email: "example@example.com" },
        { email: "example@example.com" },
      ],
    };
  },

  methods: {
    sizeRecommendationLetter() {
      return this.recommendation_letters.length;
    },
  },

  props: {
    //Cartas de recomendacion (tabla a rellenar)
    //Aqui se cambian los correos 

    images_btn:{
      type:Object,
      default:{},
    },

    appliant: {
      type: Object,
    },

    academic_program: {
      type: Object,
    },

    //recibe los emails de la carta de recomendacion como en un arreglo para comparar
    recommendation_letters: {
      type: Array,
    },

    archives_recommendation_letters: {
      type: Array,
    },

    archive_id:{
      type: Number,
      default: null
    },

  },
};
</script>